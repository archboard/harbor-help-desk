<template>
  <footer
    class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 py-6 text-sm text-gray-500 dark:text-gray-400"
  >
    <a
      href="https://archboard.io"
      target="_blank"
      rel="noopener"
      class="hover:text-gray-900 dark:hover:text-white"
    >
      {{ $t('Built by Archboard') }}
    </a>
    <a
      href="https://github.com/archboard/harbor-helpdesk"
      target="_blank"
      rel="noopener"
      class="hover:text-gray-900 dark:hover:text-white"
    >
      {{ $t('Source on GitHub') }}
    </a>
  </footer>
</template>
