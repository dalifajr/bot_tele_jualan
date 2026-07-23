/* Inline element handlers */
(() => {
  const target = document.querySelector("[data-pex=\"mx9nlsk-18\"]");
  if (!target) return;
  target.addEventListener("change", function (event) {
    const result = (function (event) {
      this.form.submit()
    }).call(this, event);

    if (result === false) {
      event.preventDefault();
      event.stopPropagation();
    }
  });
})();

(() => {
  const target = document.querySelector("[data-pex=\"mx9nlsk-89\"]");
  if (!target) return;
  target.addEventListener("change", function (event) {
    const result = (function (event) {
      this.form.submit()
    }).call(this, event);

    if (result === false) {
      event.preventDefault();
      event.stopPropagation();
    }
  });
})();