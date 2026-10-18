<template>
  <Transition
    enter-active-class="duration-300 ease-out"
    enter-from-class="-translate-y-5 opacity-0"
    enter-to-class="translate-y-0 opacity-100"
    leave-active-class="duration-200 ease-in"
    leave-from-class="translate-y-0 opacity-100"
    leave-to-class="-translate-y-5 opacity-0"
  >
    <slot />
  </Transition>
</template>
