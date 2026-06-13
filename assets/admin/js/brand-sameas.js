(function () {
  var container = document.getElementById("wseo-sameas-rows");
  var addBtn = document.getElementById("wseo-sameas-add");

  if (!container || !addBtn) {
    return;
  }

  addBtn.addEventListener("click", function () {
    var row = document.createElement("div");
    row.className = "wseo-sameas-row";
    row.style.cssText = "display:flex;gap:4px;margin-bottom:4px;";
    row.innerHTML =
      '<input type="url" name="_wseo_sameas[]" class="regular-text" style="flex:1;" placeholder="https://..." />' +
      '<button type="button" class="button wseo-sameas-remove" style="color:#a00;">&times;</button>';
    container.appendChild(row);
  });

  container.addEventListener("click", function (e) {
    if (e.target.classList.contains("wseo-sameas-remove")) {
      e.target.parentElement.remove();
    }
  });
})();
