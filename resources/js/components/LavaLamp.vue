<script setup lang="ts">
import { onBeforeUnmount, onMounted, useTemplateRef, watch } from 'vue';

const {
  colors,
  mask,
  maskClass = 'bg-white/90 dark:bg-gray-950/90',
  speed = 0.5,
} = defineProps<{
  /** CSS color values ('#047481', 'oklch(…)') or utility classes ('bg-primary-400', 'text-sky-500'). */
  colors: string[];
  /** Image URL whose alpha shapes the centered overlay; the fluid shows wherever it's transparent. */
  mask?: string;
  /** Classes painting the masked overlay. */
  maskClass?: string;
  speed?: number;
}>();

const MAX_BLOBS = 12;
// ponytail: blobs are soft, so rendering at half the device resolution is invisible and quarters the GPU work
const RENDER_SCALE = 0.5;

const vertexShader = `
attribute vec2 position;
void main() { gl_Position = vec4(position, 0.0, 1.0); }
`;

/** Metaballs: every blob adds a smooth bump that peaks gently and fades to zero at 2.2r, colors blend by each blob's share, and the field's level becomes alpha. */
const fragmentShader = `
precision highp float;
uniform vec2 resolution;
uniform float time;
uniform int count;
uniform vec3 colors[${MAX_BLOBS}];
uniform vec3 pointer;

float hash(vec2 p) { return fract(sin(dot(p, vec2(12.9898, 78.233))) * 43758.5453); }

void main() {
  float aspect = resolution.x / resolution.y;
  vec2 p = (gl_FragCoord.xy - 0.5 * resolution) / resolution.y;
  p += 0.06 * vec2(sin(p.y * 3.0 + time * 0.5), sin(p.x * 3.0 - time * 0.4));

  float field = 0.0;
  vec3 color = vec3(0.0);
  for (int i = 0; i < ${MAX_BLOBS}; i++) {
    if (i >= count) break;
    float f = float(i);
    float lane = (f + 0.5) / float(count) - 0.5;
    vec2 center = vec2(
      lane * aspect * 0.9 + 0.12 * sin(time * 0.21 + f * 2.4),
      0.55 * sin(time * (0.09 + 0.017 * f) + f * 1.9)
    );
    float radius = 0.18 + 0.04 * sin(time * 0.3 + f * 1.3);
    vec2 d = p - center;
    float falloff = max(0.0, 1.0 - dot(d, d) / (4.84 * radius * radius));
    float weight = 1.6 * falloff * falloff;
    field += weight;
    color += colors[i] * weight;
  }

  vec2 d = p - pointer.xy;
  float falloff = max(0.0, 1.0 - dot(d, d) / 0.06);
  float weight = pointer.z * 1.2 * falloff * falloff;
  field += weight;
  color += colors[0] * weight;

  color /= max(field, 0.0001);
  float alpha = smoothstep(0.2, 1.4, field);
  color += (hash(gl_FragCoord.xy + fract(time)) - 0.5) * 0.06;
  gl_FragColor = vec4(color * alpha, alpha);
}
`;

type Uniforms = Record<
  'resolution' | 'time' | 'count' | 'colors' | 'pointer',
  WebGLUniformLocation | null
>;

const root = useTemplateRef<HTMLDivElement>('root');
const canvas = useTemplateRef<HTMLCanvasElement>('canvas');
const pointer = { x: 0, y: 0, z: 0, targetX: 0, targetY: 0, target: 0 };

let gl: WebGLRenderingContext | null = null;
let uniforms: Uniforms | null = null;
let frame: number | null = null;
let resizeObserver: ResizeObserver | undefined;
let intersectionObserver: IntersectionObserver | undefined;
const start = performance.now();
/** Draw a single frame instead of animating: for reduced motion, and for software WebGL, whose frames tie up the main thread. */
let still = matchMedia('(prefers-reduced-motion: reduce)').matches;

function track(event: PointerEvent): void {
  const rect = root.value!.getBoundingClientRect();
  pointer.targetX = (event.clientX - rect.left - rect.width / 2) / rect.height;
  pointer.targetY = -(event.clientY - rect.top - rect.height / 2) / rect.height;
  pointer.target = 1;
}

/** Resolves any CSS color or utility class to sRGB 0–1, letting the browser handle oklch and friends. */
function resolveColors(): void {
  const probe = document.createElement('span');
  root.value!.append(probe);
  const context = document
    .createElement('canvas')
    .getContext('2d', { willReadFrequently: true })!;

  const rgb = colors.map((value) => {
    let color = value;
    if (!CSS.supports('color', value)) {
      probe.className = value;
      const style = getComputedStyle(probe);
      color =
        style.backgroundColor === 'rgba(0, 0, 0, 0)'
          ? style.color
          : style.backgroundColor;
    }
    context.fillStyle = color;
    context.fillRect(0, 0, 1, 1);
    const [r, g, b] = context.getImageData(0, 0, 1, 1).data;

    return [r / 255, g / 255, b / 255];
  });
  probe.remove();

  const count = Math.min(MAX_BLOBS, Math.max(6, rgb.length * 2));
  const blobColors = new Float32Array(MAX_BLOBS * 3);
  for (let i = 0; i < count; i++) {
    blobColors.set(rgb[i % rgb.length], i * 3);
  }
  gl!.uniform1i(uniforms!.count, count);
  gl!.uniform3fv(uniforms!.colors, blobColors);
}

/** Chrome masks RENDERER as 'WebKit WebGL'; Firefox exposes it directly and deprecates the debug extension. */
function isSoftwareRendered(context: WebGLRenderingContext): boolean {
  let renderer = context.getParameter(context.RENDERER) as string;
  if (renderer === 'WebKit WebGL') {
    const debugInfo = context.getExtension('WEBGL_debug_renderer_info');
    renderer = debugInfo
      ? context.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL)
      : renderer;
  }

  return /swiftshader|llvmpipe|software/i.test(renderer);
}

function compile(
  context: WebGLRenderingContext,
  type: number,
  source: string,
): WebGLShader {
  const shader = context.createShader(type)!;
  context.shaderSource(shader, source);
  context.compileShader(shader);

  return shader;
}

function draw(now: number): void {
  pointer.x += (pointer.targetX - pointer.x) * 0.04;
  pointer.y += (pointer.targetY - pointer.y) * 0.04;
  pointer.z += (pointer.target - pointer.z) * 0.04;
  gl!.uniform1f(uniforms!.time, still ? 20 : ((now - start) / 1000) * speed);
  gl!.uniform3f(uniforms!.pointer, pointer.x, pointer.y, pointer.z);
  gl!.drawArrays(gl!.TRIANGLES, 0, 3);
}

function loop(now: number): void {
  draw(now);
  frame = requestAnimationFrame(loop);
}

function play(): void {
  if (!still && !frame) {
    frame = requestAnimationFrame(loop);
  }
}

function pause(): void {
  if (frame) {
    cancelAnimationFrame(frame);
  }
  frame = null;
}

function resize(): void {
  const element = canvas.value!;
  const scale = devicePixelRatio * RENDER_SCALE;
  element.width = Math.ceil(element.clientWidth * scale);
  element.height = Math.ceil(element.clientHeight * scale);
  gl!.viewport(0, 0, element.width, element.height);
  gl!.uniform2f(uniforms!.resolution, element.width, element.height);
  draw(performance.now());
}

onMounted(() => {
  const context = canvas.value!.getContext('webgl', {
    antialias: false,
    depth: false,
  });
  if (!context) {
    return;
  }
  gl = context;
  still ||= isSoftwareRendered(context);

  const program = context.createProgram()!;
  context.attachShader(
    program,
    compile(context, context.VERTEX_SHADER, vertexShader),
  );
  context.attachShader(
    program,
    compile(context, context.FRAGMENT_SHADER, fragmentShader),
  );
  context.bindAttribLocation(program, 0, 'position');
  context.linkProgram(program);
  if (!context.getProgramParameter(program, context.LINK_STATUS)) {
    console.error(context.getProgramInfoLog(program));
    return;
  }
  context.useProgram(program);

  context.bindBuffer(context.ARRAY_BUFFER, context.createBuffer());
  context.bufferData(
    context.ARRAY_BUFFER,
    new Float32Array([-1, -1, 3, -1, -1, 3]),
    context.STATIC_DRAW,
  );
  context.enableVertexAttribArray(0);
  context.vertexAttribPointer(0, 2, context.FLOAT, false, 0, 0);

  uniforms = Object.fromEntries(
    (['resolution', 'time', 'count', 'colors', 'pointer'] as const).map(
      (name) => [name, context.getUniformLocation(program, name)],
    ),
  ) as Uniforms;
  resolveColors();

  resizeObserver = new ResizeObserver(resize);
  resizeObserver.observe(canvas.value!);
  intersectionObserver = new IntersectionObserver(([entry]) =>
    entry.isIntersecting ? play() : pause(),
  );
  intersectionObserver.observe(canvas.value!);
});

watch(
  () => colors,
  () => {
    if (uniforms) {
      resolveColors();
      draw(performance.now());
    }
  },
  { deep: true },
);

onBeforeUnmount(() => {
  pause();
  resizeObserver?.disconnect();
  intersectionObserver?.disconnect();
  gl?.getExtension('WEBGL_lose_context')?.loseContext();
});
</script>

<template>
  <div
    ref="root"
    class="relative overflow-hidden"
    aria-hidden="true"
    @pointermove="track"
    @pointerleave="pointer.target = 0"
  >
    <canvas ref="canvas" class="absolute inset-0 size-full" />
    <div
      v-if="mask"
      class="absolute inset-0 m-auto aspect-square w-1/2 max-w-100 mask-contain mask-center mask-no-repeat"
      :class="maskClass"
      :style="{ maskImage: `url(${mask})` }"
    />
  </div>
</template>
