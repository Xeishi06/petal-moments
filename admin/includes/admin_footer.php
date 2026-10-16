</main>
    </div>
</div>

<div class="modal-overlay" id="logoutModal" aria-hidden="true">
    <div class="modal-card auth-modal-card" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle">
        <button type="button" class="modal-close" data-logout-close aria-label="Close">&#10005;</button>
        <div class="auth-modal-body">
            <span class="auth-modal-heart" aria-hidden="true">&#128075;</span>
            <span class="logout-eyebrow">See You Soon</span>
            <h2 id="logoutModalTitle">Log out of <em>Petal Moments?</em></h2>
            <p>You'll need to log back in to manage the shop.</p>
            <div class="auth-modal-actions">
                <button type="button" class="btn btn-light" data-logout-close>Stay Logged In</button>
                <a class="btn btn-primary" id="logoutConfirm" href="../logout.php">Log Out</a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
  var flashes = document.querySelectorAll(".flash-alert");
  if (flashes.length) {
    setTimeout(function () {
      flashes.forEach(function (el) { el.classList.add("flash-hide"); });
      setTimeout(function () { flashes.forEach(function (el) { el.remove(); }); }, 550);
    }, 4000);
  }

  var modal = document.getElementById("logoutModal");
  var confirmBtn = document.getElementById("logoutConfirm");
  if (!modal || !confirmBtn) return;

  function openLogout(href) {
    if (href) confirmBtn.setAttribute("href", href);
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
  }
  function closeLogout() {
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
  }

  document.querySelectorAll('[data-logout-confirm], a[href="../logout.php"], a[href="logout.php"]').forEach(function (link) {
    if (link.id === "logoutConfirm") return;
    link.addEventListener("click", function (e) {
      e.preventDefault();
      openLogout(link.getAttribute("href"));
    });
  });
  document.querySelectorAll("[data-logout-close]").forEach(function (btn) {
    btn.addEventListener("click", closeLogout);
  });
  modal.addEventListener("click", function (e) {
    if (e.target === modal) closeLogout();
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeLogout();
  });
})();
</script>

</body>
</html>
