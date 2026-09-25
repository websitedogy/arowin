(function () {
  const stateEl = document.getElementById("glState");
  const districtEl = document.getElementById("glDistrict");
  const map = window.AROWIN_GUEST_DISTRICTS || {};
  const overlay = document.getElementById("glOverlay");
  const openBtn = document.getElementById("glOpenModal");
  const closeBtn = document.getElementById("glCloseModal");
  const cancelBtn = document.getElementById("glCancelModal");
  const joinOverlay = document.getElementById("glJoinOverlay");
  const joinClose = document.getElementById("glJoinClose");
  const joinCancel = document.getElementById("glJoinCancel");
  const credOverlay = document.getElementById("glCredOverlay");
  const credClose = document.getElementById("glCredClose");
  const credPassToggle = document.getElementById("glCredPassToggle");
  const credPassEl = document.getElementById("glCredPassword");

  function fillDistricts(state, selected) {
    if (!districtEl) return;
    const list = map[state] || [];
    districtEl.innerHTML = "";
    const first = document.createElement("option");
    first.value = "";
    first.textContent = state ? "Select district" : "Select state first";
    districtEl.appendChild(first);
    list.forEach((name) => {
      const opt = document.createElement("option");
      opt.value = name;
      opt.textContent = name;
      if (selected && selected === name) opt.selected = true;
      districtEl.appendChild(opt);
    });
  }

  function anyOverlayOpen() {
    return (
      (overlay && !overlay.classList.contains("hidden")) ||
      (joinOverlay && !joinOverlay.classList.contains("hidden")) ||
      (credOverlay && !credOverlay.classList.contains("hidden"))
    );
  }

  function openModal() {
    if (!overlay) return;
    overlay.classList.remove("hidden");
    overlay.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
    const firstInput = overlay.querySelector('input[name="full_name"]');
    if (firstInput) setTimeout(() => firstInput.focus(), 50);
  }

  function closeModal() {
    if (!overlay) return;
    overlay.classList.add("hidden");
    overlay.setAttribute("aria-hidden", "true");
    if (!anyOverlayOpen()) document.body.style.overflow = "";
  }

  function openJoinModal(data) {
    if (!joinOverlay) return;
    const idEl = document.getElementById("glJoinGuestId");
    const nameEl = document.getElementById("glJoinName");
    const mobileEl = document.getElementById("glJoinMobile");
    if (idEl && data && data.id != null) idEl.value = String(data.id);
    if (nameEl && data && data.name != null) nameEl.value = String(data.name);
    if (mobileEl && data && data.phone != null) mobileEl.value = String(data.phone);
    joinOverlay.classList.remove("hidden");
    joinOverlay.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
    if (nameEl) setTimeout(() => nameEl.focus(), 50);
  }

  function closeJoinModal() {
    if (!joinOverlay) return;
    joinOverlay.classList.add("hidden");
    joinOverlay.setAttribute("aria-hidden", "true");
    if (!anyOverlayOpen()) document.body.style.overflow = "";
  }

  function closeCredModal() {
    if (!credOverlay) return;
    credOverlay.classList.add("hidden");
    if (!anyOverlayOpen()) document.body.style.overflow = "";
  }

  if (stateEl && districtEl) {
    stateEl.addEventListener("change", () => {
      fillDistricts(stateEl.value, "");
    });
    fillDistricts(stateEl.value, districtEl.getAttribute("data-selected") || "");
  }

  openBtn?.addEventListener("click", openModal);
  closeBtn?.addEventListener("click", closeModal);
  cancelBtn?.addEventListener("click", closeModal);
  overlay?.addEventListener("click", (e) => {
    if (e.target === overlay) closeModal();
  });

  joinClose?.addEventListener("click", closeJoinModal);
  joinCancel?.addEventListener("click", closeJoinModal);
  joinOverlay?.addEventListener("click", (e) => {
    if (e.target === joinOverlay) closeJoinModal();
  });

  credClose?.addEventListener("click", closeCredModal);
  credOverlay?.addEventListener("click", (e) => {
    if (e.target === credOverlay) closeCredModal();
  });
  credPassToggle?.addEventListener("click", () => {
    if (!credPassEl || !credPassToggle) return;
    const real = credPassEl.getAttribute("data-pass") || "";
    const showing = credPassToggle.classList.contains("is-visible");
    if (showing) {
      credPassEl.textContent = "••••••";
      credPassToggle.classList.remove("is-visible");
      credPassToggle.setAttribute("aria-label", "Show password");
    } else {
      credPassEl.textContent = real;
      credPassToggle.classList.add("is-visible");
      credPassToggle.setAttribute("aria-label", "Hide password");
    }
  });

  function loadHtml2Canvas() {
    return new Promise(function (resolve, reject) {
      if (window.html2canvas) {
        resolve(window.html2canvas);
        return;
      }
      const s = document.createElement("script");
      s.src = "https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js";
      s.onload = function () {
        resolve(window.html2canvas);
      };
      s.onerror = function () {
        reject(new Error("html2canvas failed"));
      };
      document.head.appendChild(s);
    });
  }

  async function shareCredWhatsApp() {
    const btn = document.getElementById("glCredWaBtn");
    const card = document.getElementById("glCredCard");
    if (!card) return;

    const uid = card.getAttribute("data-user-id") || "";
    const pw = card.getAttribute("data-password") || "";
    const sid = card.getAttribute("data-sponsor-id") || "";
    const sname = card.getAttribute("data-sponsor-name") || "";
    const dt = card.getAttribute("data-date") || "";
    const loginLink = card.getAttribute("data-login") || "";
    const support = card.getAttribute("data-support") || "support@arowin.com";
    const text =
      "Welcome to Arowin company!\n" +
      "Bright future ahead — let's grow together with Arowin.\n\n" +
      "Arowin Registration\n" +
      "User ID: " +
      uid +
      "\n" +
      "Password: " +
      pw +
      "\n" +
      "Sponsor ID: " +
      sid +
      "\n" +
      "Sponsor name: " +
      sname +
      "\n" +
      "Date: " +
      dt +
      "\n\n" +
      "Need help? " +
      support +
      "\n" +
      "Sign in: " +
      loginLink;

    // Show password in card so shared image includes it
    const prevPass = credPassEl ? credPassEl.textContent : "";
    const wasVisible = credPassToggle && credPassToggle.classList.contains("is-visible");
    if (credPassEl) credPassEl.textContent = pw || "••••••";

    if (btn) btn.disabled = true;
    try {
      const html2canvas = await loadHtml2Canvas();
      const canvas = await html2canvas(card, {
        backgroundColor: "#141414",
        scale: 2,
        useCORS: true,
        ignoreElements: function (el) {
          return el.classList && el.classList.contains("no-capture");
        },
      });
      const blob = await new Promise(function (resolve) {
        canvas.toBlob(function (b) {
          resolve(b);
        }, "image/png");
      });
      if (!blob) throw new Error("No image");

      const file = new File([blob], "arowin-registration.png", { type: "image/png" });
      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        await navigator.share({
          files: [file],
          title: "Arowin Registration",
          text: text,
        });
        return;
      }

      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = "arowin-registration-" + uid + ".png";
      document.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(function () {
        URL.revokeObjectURL(url);
      }, 2000);
      window.open(
        "https://wa.me/?text=" +
          encodeURIComponent(text + "\n\n(Image downloaded — attach in WhatsApp)"),
        "_blank"
      );
    } catch (err) {
      window.open("https://wa.me/?text=" + encodeURIComponent(text), "_blank");
    } finally {
      if (credPassEl) {
        if (wasVisible) {
          credPassEl.textContent = pw;
        } else {
          credPassEl.textContent = prevPass || "••••••";
        }
      }
      if (btn) btn.disabled = false;
    }
  }

  document.getElementById("glCredWaBtn")?.addEventListener("click", shareCredWhatsApp);

  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    if (credOverlay && !credOverlay.classList.contains("hidden")) {
      closeCredModal();
      return;
    }
    if (joinOverlay && !joinOverlay.classList.contains("hidden")) {
      closeJoinModal();
      return;
    }
    if (overlay && !overlay.classList.contains("hidden")) {
      closeModal();
    }
  });

  document.querySelectorAll(".gl-leg input[type=radio]").forEach((radio) => {
    radio.addEventListener("change", () => {
      document.querySelectorAll(".gl-leg").forEach((lab) => lab.classList.remove("is-on"));
      const parent = radio.closest(".gl-leg");
      if (parent) parent.classList.add("is-on");
    });
  });

  document.querySelectorAll(".gl-pass-toggle").forEach((btn) => {
    btn.addEventListener("click", () => {
      const id = btn.getAttribute("data-gl-pass") || "";
      const input = id ? document.getElementById(id) : null;
      if (!input) return;
      const show = input.type === "password";
      input.type = show ? "text" : "password";
      btn.classList.toggle("is-visible", show);
      btn.setAttribute("aria-label", show ? "Hide password" : "Show password");
    });
  });

  const joinFormEl = document.getElementById("glJoinForm");
  const joinPw = document.getElementById("glJoinPassword");
  const joinPw2 = document.getElementById("glJoinPassword2");
  const passHint = document.getElementById("glPassHint");
  const joinErrors = document.getElementById("glJoinErrors");

  function setJoinError(msg) {
    if (!joinErrors) return;
    if (!msg) {
      joinErrors.textContent = "";
      joinErrors.classList.add("hidden");
      joinErrors.hidden = true;
      return;
    }
    joinErrors.textContent = msg;
    joinErrors.classList.remove("hidden");
    joinErrors.hidden = false;
  }

  function validateJoinPasswords(showOk) {
    if (!joinPw || !joinPw2 || !passHint) return true;
    const a = String(joinPw.value || "");
    const b = String(joinPw2.value || "");
    joinPw.classList.remove("is-invalid");
    joinPw2.classList.remove("is-invalid");
    passHint.hidden = true;
    passHint.classList.remove("is-error", "is-ok");
    passHint.textContent = "";

    if (a === "" && b === "") return true;

    if (a.length > 0 && a.length < 6) {
      passHint.hidden = false;
      passHint.classList.add("is-error");
      passHint.textContent = "Password must be at least 6 characters.";
      joinPw.classList.add("is-invalid");
      return false;
    }
    if (b.length > 0 && a !== b) {
      passHint.hidden = false;
      passHint.classList.add("is-error");
      passHint.textContent = "Passwords do not match.";
      joinPw.classList.add("is-invalid");
      joinPw2.classList.add("is-invalid");
      return false;
    }
    if (showOk && a.length >= 6 && a === b) {
      passHint.hidden = false;
      passHint.classList.add("is-ok");
      passHint.textContent = "Passwords match.";
    }
    return true;
  }

  joinPw?.addEventListener("input", () => validateJoinPasswords(true));
  joinPw2?.addEventListener("input", () => validateJoinPasswords(true));

  joinFormEl?.addEventListener("submit", (e) => {
    const a = String(joinPw?.value || "");
    const b = String(joinPw2?.value || "");
    if (a === "") {
      e.preventDefault();
      setJoinError("Password is required.");
      joinPw?.classList.add("is-invalid");
      joinPw?.focus();
      return;
    }
    if (a.length < 6) {
      e.preventDefault();
      setJoinError("Password must be at least 6 characters.");
      validateJoinPasswords(false);
      joinPw?.focus();
      return;
    }
    if (a !== b) {
      e.preventDefault();
      setJoinError("Passwords do not match.");
      validateJoinPasswords(false);
      joinPw2?.focus();
      return;
    }
    setJoinError("");
  });

  // Clear password fields when opening join for a new guest
  const _openJoinModal = openJoinModal;
  openJoinModal = function (data) {
    _openJoinModal(data);
    if (joinPw) joinPw.value = "";
    if (joinPw2) joinPw2.value = "";
    // Fresh open from Action → clear errors; server reopen keeps PHP error box.
    if (data) setJoinError("");
    if (passHint) {
      passHint.hidden = true;
      passHint.textContent = "";
      passHint.classList.remove("is-error", "is-ok");
    }
    joinPw?.classList.remove("is-invalid");
    joinPw2?.classList.remove("is-invalid");
  };

  if (document.body.getAttribute("data-gl-open-modal") === "1") {
    openModal();
  }
  if (document.body.getAttribute("data-gl-open-join") === "1") {
    openJoinModal(null);
  }
  if (document.body.getAttribute("data-gl-join-success") === "1" && credOverlay) {
    document.body.style.overflow = "hidden";
  }

  window.arowinGuestAction = function (sel) {
    if (!sel) return;
    const value = String(sel.value || "");
    if (value === "join") {
      openJoinModal({
        id: sel.getAttribute("data-guest-id") || "",
        name: sel.getAttribute("data-guest-name") || "",
        phone: sel.getAttribute("data-guest-phone") || "",
      });
      sel.value = "";
      return;
    }
    if (value === "dropped") {
      if (sel.form) sel.form.submit();
      return;
    }
    sel.value = "";
  };
})();
