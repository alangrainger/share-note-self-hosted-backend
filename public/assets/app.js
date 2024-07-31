function initDocument () {
  /*
   * Callout fold/unfold
   */
  document.querySelectorAll('.callout.is-collapsible > .callout-title').forEach(titleEl => {
    // Add a listener on the title element
    titleEl.addEventListener('click', () => {
      const calloutEl = titleEl.parentElement
      // Toggle the collapsed class
      calloutEl.classList.toggle('is-collapsed')
      titleEl.querySelector('.callout-fold').classList.toggle('is-collapsed')
      // Show/hide the content
      calloutEl.querySelector('.callout-content').style.display = calloutEl.classList.contains('is-collapsed') ? 'none' : ''
    })
  })

  /*
   * Light/Dark theme toggle
   */
  const themeToggleEl = document.querySelector('#theme-mode-toggle')
  themeToggleEl.onclick = () => {
    document.body.classList.toggle('theme-dark')
    document.body.classList.toggle('theme-light')
  }
}
