</main>
    </div>
</div>

<script>
(function () {
  var flashes = document.querySelectorAll(".flash-alert");
  if (!flashes.length) return;
  setTimeout(function () {
    flashes.forEach(function (el) { el.classList.add("flash-hide"); });
    setTimeout(function () { flashes.forEach(function (el) { el.remove(); }); }, 550);
  }, 4000);
})();
</script>

</body>
</html>