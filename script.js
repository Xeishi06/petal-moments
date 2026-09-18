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

document.querySelectorAll('.main-nav a[href^="index.php#"]').forEach((link) => {
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
