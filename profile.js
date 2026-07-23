/* Inline element handlers */
(() => {
  const target = document.querySelector("[data-pex=\"d8kwog4-155\"]");
  if (!target) return;
  target.addEventListener("change", function (event) {
    const result = (function (event) {
      previewImage(this)
    }).call(this, event);

    if (result === false) {
      event.preventDefault();
      event.stopPropagation();
    }
  });
})();

/* Inline script */
/* Matched element tokens: photo */

    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('photoPreview').src = e.target.result;
                document.getElementById('previewContainer').classList.remove('d-none');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
