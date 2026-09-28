const menuBtn = document.getElementById("menuBtn");
const mainNav = document.getElementById("mainNav");

menuBtn.addEventListener("click", () => {
  const isOpen = mainNav.classList.toggle("open");
  menuBtn.setAttribute("aria-expanded", isOpen);
});

mainNav.querySelectorAll("a").forEach((link) => {
  link.addEventListener("click", () => {
    mainNav.classList.remove("open");
    menuBtn.setAttribute("aria-expanded", "false");
  });
});

// ===== Smooth in-page scroll for nav anchor links =====
const onHomePage = /(^|\/)index\.php($|\?)/.test(window.location.pathname) || window.location.pathname === "/petal-moments/";

document.querySelectorAll('a[href^="index.php#"]').forEach((link) => {
  link.addEventListener("click", (e) => {
    const hash = link.getAttribute("href").split("#")[1];
    const target = document.getElementById(hash);
    if (onHomePage && target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: "smooth" });
      history.replaceState(null, "", "#" + hash);
    }
  });
});

// ===== Event inquiry modal =====
const modal = document.getElementById("eventModal");

function openModal() {
  if (!modal) return;
  modal.classList.add("open");
  modal.setAttribute("aria-hidden", "false");
  document.body.classList.add("modal-open");
}
function closeModal() {
  if (!modal) return;
  modal.classList.remove("open");
  modal.setAttribute("aria-hidden", "true");
  document.body.classList.remove("modal-open");
  const msg = modal.querySelector(".modal-message");
  if (msg) { msg.textContent = ""; msg.className = "modal-message"; }
}

document.querySelectorAll("[data-modal-open]").forEach((btn) => {
  btn.addEventListener("click", openModal);
});
document.querySelectorAll("[data-modal-close]").forEach((btn) => {
  btn.addEventListener("click", closeModal);
});

if (modal) {
  modal.addEventListener("click", (e) => {
    if (e.target === modal) closeModal();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeModal();
  });

  const form = modal.querySelector("#eventInquiryForm");
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const msg = modal.querySelector(".modal-message");
    msg.textContent = "";
    msg.className = "modal-message";

    const data = new FormData(form);
    try {
      const res = await fetch("event_inquiry.php", { method: "POST", body: data });
      const json = await res.json();
      if (json.ok) {
        msg.textContent = "Thanks! Your inquiry has been sent. We'll contact you soon.";
        msg.className = "modal-message success";
        form.reset();
      } else {
        msg.textContent = json.error || "Something went wrong. Please try again.";
        msg.className = "modal-message error";
      }
    } catch (err) {
      msg.textContent = "Network error. Please try again.";
      msg.className = "modal-message error";
    }
  });
}

// ===== Logout confirmation modal =====
(function () {
  const modal = document.getElementById("logoutModal");
  const confirmBtn = document.getElementById("logoutConfirm");
  if (!modal || !confirmBtn) return;
  function openLogout(href) {
    confirmBtn.setAttribute("href", href);
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
  }
  function closeLogout() {
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
  }
  document.querySelectorAll('a[href="logout.php"], a[href="../logout.php"]').forEach((link) => {
    // Skip the confirm button itself and mobile-menu duplicates handled below
    if (link.id === "logoutConfirm") return;
    link.addEventListener("click", (e) => {
      e.preventDefault();
      // Close the mobile nav if it's open
      const nav = document.getElementById("mainNav");
      if (nav) nav.classList.remove("open");
      openLogout(link.getAttribute("href"));
    });
  });
  document.querySelectorAll("[data-logout-close]").forEach((btn) => {
    btn.addEventListener("click", closeLogout);
  });
  modal.addEventListener("click", (e) => {
    if (e.target === modal) closeLogout();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeLogout();
  });
})();

// ===== Auto-dismiss flash messages after 4s =====
(function () {
  const flashes = document.querySelectorAll(".flash-alert");
  if (!flashes.length) return;
  setTimeout(() => {
    flashes.forEach((el) => el.classList.add("flash-hide"));
    setTimeout(() => flashes.forEach((el) => el.remove()), 550);
  }, 4000);
})();

// ===== Auth-required modal (pretty login nudge) =====
(function () {
  const modal = document.getElementById("authModal");
  function openAuth() {
    if (!modal) return;
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
  }
  function closeAuth() {
    if (!modal) return;
    modal.classList.remove("open");
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
  }
  document.querySelectorAll("[data-auth-required]").forEach((el) => {
    el.addEventListener("click", openAuth);
  });
  document.querySelectorAll("[data-auth-close]").forEach((btn) => {
    btn.addEventListener("click", closeAuth);
  });
  if (modal) {
    modal.addEventListener("click", (e) => {
      if (e.target === modal) closeAuth();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeAuth();
    });
  }
  // Expose for the wishlist buttons below
  window.openAuthModal = openAuth;
})();

// ===== Wishlist hearts (login modal for guests, toggle for members) =====
(function () {
  const isLoggedIn = document.body.dataset.auth === "1";
  const csrf = document.body.dataset.csrf || "";
  document.querySelectorAll("[data-wishlist]").forEach((btn) => {
    btn.addEventListener("click", async (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (!isLoggedIn) {
        if (window.openAuthModal) window.openAuthModal();
        return;
      }
      const pid = btn.getAttribute("data-wishlist");
      btn.disabled = true;
      try {
        const body = new FormData();
        body.append("product_id", pid);
        body.append("csrf", csrf);
        const res = await fetch("wishlist_toggle.php", { method: "POST", body });
        const json = await res.json();
        if (json.ok) {
          const saved = !!json.saved;
          btn.classList.toggle("active", saved);
          btn.setAttribute("aria-pressed", saved ? "true" : "false");
          // If we're on the favorites page and it was removed, drop the card
          if (!saved && window.location.pathname.includes("wishlist.php")) {
            const card = btn.closest(".product-card");
            if (card) card.remove();
            if (!document.querySelector(".product-card")) window.location.reload();
          }
        }
      } catch (err) {
        /* silent — heart just won't toggle */
      } finally {
        btn.disabled = false;
      }
    });
  });
})();

// ===== Budget auto-format: 2000 -> 2,000 while typing =====
(function () {
  const fields = document.querySelectorAll(
    '#eventInquiryForm input[name="budget"], form[action="events.php"] input[name="budget"]'
  );
  if (!fields.length) return;

  function formatMoney(raw) {
    const clean = raw.replace(/[^0-9.]/g, "");
    if (clean === "") return "";
    const parts = clean.split(".");
    const intPart = parts[0].replace(/^0+(?=\d)/, "") || "0";
    const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    if (parts.length === 1) return grouped;
    return grouped + "." + parts.slice(1).join("").slice(0, 2);
  }

  fields.forEach((input) => {
    input.setAttribute("inputmode", "decimal");
    input.addEventListener("input", () => {
      const pos = input.selectionStart;
      const before = input.value.length;
      input.value = formatMoney(input.value);
      const after = input.value.length;
      try { input.setSelectionRange(pos + (after - before), pos + (after - before)); } catch (e) {}
    });
    // On blur, pad to exactly 2 decimals: 2000 -> 2,000.00
    input.addEventListener("blur", () => {
      const clean = input.value.replace(/,/g, "");
      if (clean === "" || isNaN(parseFloat(clean))) return;
      input.value = parseFloat(clean).toLocaleString("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    });
    // Strip commas before the form actually sends
    const form = input.closest("form");
    if (form && !form.dataset.moneyBound) {
      form.dataset.moneyBound = "1";
      form.addEventListener("submit", () => {
        input.value = input.value.replace(/,/g, "");
      });
    }
  });
})();
// ===== Hero slideshow: crossfade bestsellers every 5s, arrows + click to buy =====
(function () {
  const slides = window.heroSlides || [];
  if (slides.length < 2) return;
  const photos = document.querySelectorAll(".hero-main-photo img.hero-slide");
  const card = document.getElementById("heroCard");
  const nameEl = document.getElementById("heroName");
  const priceEl = document.getElementById("heroPrice");
  const prevBtn = document.getElementById("heroPrev");
  const nextBtn = document.getElementById("heroNext");
  if (!photos.length || !nameEl || !priceEl) return;

  let i = 0;
  let timer = null;

  function productUrl(idx) {
    const id = slides[idx] && slides[idx].id ? parseInt(slides[idx].id, 10) : 0;
    return id > 0 ? "product.php?id=" + id : "shop.php";
  }

  function goTo(next) {
    next = (next + slides.length) % slides.length;
    if (next === i) return;
    photos[next].classList.add("active");
    photos[i].classList.remove("active");
    if (card) card.classList.add("hero-fade");
    setTimeout(() => {
      nameEl.textContent = slides[next].name;
      priceEl.textContent = slides[next].price;
      if (card) {
        card.setAttribute("href", productUrl(next));
        card.classList.remove("hero-fade");
      }
    }, 450);
    i = next;
  }

  function restartAuto() {
    if (timer) clearInterval(timer);
    timer = setInterval(() => goTo(i + 1), 5000);
  }

  if (prevBtn) prevBtn.addEventListener("click", (e) => { e.stopPropagation(); goTo(i - 1); restartAuto(); });
  if (nextBtn) nextBtn.addEventListener("click", (e) => { e.stopPropagation(); goTo(i + 1); restartAuto(); });

  // Click photo to view/buy that product
  photos.forEach((img) => {
    img.addEventListener("click", () => {
      const href = img.getAttribute("data-href") || productUrl(i);
      window.location.href = href;
    });
  });

  restartAuto();
})();
