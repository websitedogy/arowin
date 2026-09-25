(function () {
  const api = window.AROWIN_ACCOUNT_API || "";
  const state = window.AROWIN_SECURITY || {};
  const signinToggle = document.getElementById("signinToggle");
  const signinBadge = document.getElementById("signinBadge");
  const bioCard = document.getElementById("bioCard");
  const bioToggle = document.getElementById("bioToggle");
  const bioBadge = document.getElementById("bioBadge");
  const twoFaToggle = document.getElementById("twoFaToggle");
  const twoFaBadge = document.getElementById("twoFaBadge");
  const overlay = document.getElementById("secOverlay");
  const sheetTitle = document.getElementById("secSheetTitle");
  const sheetSub = document.getElementById("secSheetSub");
  const form = document.getElementById("secPinForm");
  const pin1 = document.getElementById("secPin");
  const pin2 = document.getElementById("secPin2");
  const pin2Label = document.getElementById("secPin2Label");
  const msg = document.getElementById("secMsg");
  const submitBtn = document.getElementById("secSubmit");
  let mode = "enable"; // enable | disable
  let syncingUi = false;

  function isArowinMobileBioContext() {
    const ua = navigator.userAgent || "";
    if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|CriOS|FxiOS/i.test(ua)) {
      return true;
    }
    if (navigator.platform === "MacIntel" && (navigator.maxTouchPoints || 0) > 1) {
      return true;
    }
    return false;
  }

  const mobileBioOk = isArowinMobileBioContext();
  if (bioCard) {
    bioCard.hidden = !mobileBioOk;
    bioCard.style.display = mobileBioOk ? "" : "none";
  }

  function b64url(buf) {
    const bytes = buf instanceof ArrayBuffer ? new Uint8Array(buf) : buf;
    let str = "";
    bytes.forEach((b) => {
      str += String.fromCharCode(b);
    });
    return btoa(str).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
  }

  async function post(action, data) {
    const body = new FormData();
    body.append("action", action);
    Object.keys(data || {}).forEach((k) => body.append(k, data[k]));
    const res = await fetch(api, { method: "POST", body, credentials: "same-origin" });
    return res.json();
  }

  function showMsg(text, ok) {
    if (!msg) return;
    msg.hidden = false;
    msg.textContent = text;
    msg.className = "id-sec-msg " + (ok ? "is-ok" : "is-err");
  }

  function applyMode(loginMode) {
    const modeName = loginMode === "2fa" || loginMode === "biometric" ? loginMode : "signin";
    state.login_mode = modeName;
    state.two_fa_enabled = modeName === "2fa";
    state.biometric_enabled = modeName === "biometric";

    syncingUi = true;
    if (signinToggle) signinToggle.checked = modeName === "signin";
    if (bioToggle) bioToggle.checked = modeName === "biometric";
    if (twoFaToggle) twoFaToggle.checked = modeName === "2fa";
    syncingUi = false;

    if (signinBadge) {
      signinBadge.textContent = modeName === "signin" ? "Enabled" : "Disabled";
      signinBadge.classList.toggle("is-on", modeName === "signin");
    }
    if (bioBadge) {
      bioBadge.textContent = modeName === "biometric" ? "Enabled" : "Disabled";
      bioBadge.classList.toggle("is-on", modeName === "biometric");
    }
    if (twoFaBadge) {
      twoFaBadge.textContent = modeName === "2fa" ? "Enabled" : "Disabled";
      twoFaBadge.classList.toggle("is-on", modeName === "2fa");
    }

    try {
      if (modeName === "2fa" && state.username) {
        localStorage.setItem("arowin_pwa_uid", String(state.username).toUpperCase());
        localStorage.setItem("arowin_pwa_2fa", "1");
      } else {
        localStorage.removeItem("arowin_pwa_2fa");
      }
    } catch (_) {}
  }

  function applySecurityPayload(security) {
    if (!security || typeof security !== "object") return;
    applyMode(security.login_mode || (security.biometric_enabled ? "biometric" : security.two_fa_enabled ? "2fa" : "signin"));
  }

  function openSheet(nextMode) {
    mode = nextMode;
    if (!overlay) return;
    overlay.classList.remove("hidden");
    overlay.setAttribute("aria-hidden", "false");
    if (sheetTitle) sheetTitle.textContent = mode === "enable" ? "Enable 2FA" : "Disable 2FA";
    if (sheetSub) {
      sheetSub.textContent =
        mode === "enable"
          ? "Create a 6-digit PIN. This turns off Sign In and Fingerprint."
          : "Enter your current 6-digit PIN to disable 2FA (returns to Sign In).";
    }
    if (pin2) pin2.required = mode === "enable";
    if (pin2) pin2.style.display = mode === "enable" ? "" : "none";
    if (pin2Label) pin2Label.style.display = mode === "enable" ? "" : "none";
    if (submitBtn) submitBtn.textContent = mode === "enable" ? "Enable 2FA" : "Disable 2FA";
    if (msg) msg.hidden = true;
    if (form) form.reset();
    pin1?.focus();
  }

  function closeSheet() {
    if (!overlay) return;
    overlay.classList.add("hidden");
    overlay.setAttribute("aria-hidden", "true");
    // Re-sync toggles if user cancelled enable
    applyMode(state.login_mode || "signin");
  }

  document.getElementById("secClose")?.addEventListener("click", closeSheet);
  document.getElementById("secCancel")?.addEventListener("click", closeSheet);
  overlay?.addEventListener("click", (e) => {
    if (e.target === overlay) closeSheet();
  });

  form?.addEventListener("submit", async (e) => {
    e.preventDefault();
    const pin = (pin1?.value || "").trim();
    const confirm = (pin2?.value || "").trim();
    try {
      const result =
        mode === "enable"
          ? await post("enable_2fa", { pin, confirm_pin: confirm })
          : await post("disable_2fa", { pin });
      if (!result.ok) {
        showMsg(result.message || "Request failed.", false);
        return;
      }
      applySecurityPayload(result.security || { login_mode: mode === "enable" ? "2fa" : "signin" });
      showMsg(result.message || "Saved.", true);
      setTimeout(closeSheet, 700);
    } catch (_) {
      showMsg("Network error. Try again.", false);
    }
  });

  signinToggle?.addEventListener("change", async () => {
    if (syncingUi) return;
    if (!signinToggle.checked) {
      // Must keep one mode — reverting to previous
      applyMode(state.login_mode || "signin");
      return;
    }
    if (state.login_mode === "signin") return;
    try {
      const result = await post("enable_signin", {});
      if (!result.ok) {
        applyMode(state.login_mode || "signin");
        alert(result.message || "Could not enable Sign In.");
        return;
      }
      applySecurityPayload(result.security || { login_mode: "signin" });
    } catch (_) {
      applyMode(state.login_mode || "signin");
      alert("Network error. Try again.");
    }
  });

  twoFaToggle?.addEventListener("change", () => {
    if (syncingUi) return;
    if (twoFaToggle.checked) {
      // Don't leave checked until PIN sheet succeeds
      syncingUi = true;
      twoFaToggle.checked = false;
      syncingUi = false;
      openSheet("enable");
      return;
    }
    // Turning off 2FA → need PIN confirm
    syncingUi = true;
    twoFaToggle.checked = true;
    syncingUi = false;
    openSheet("disable");
  });

  bioToggle?.addEventListener("change", async () => {
    if (syncingUi) return;
    const wantOn = !!bioToggle.checked;
    if (!wantOn) {
      try {
        const result = await post("disable_biometric", {});
        if (!result.ok) {
          applyMode(state.login_mode || "biometric");
          alert(result.message || "Could not disable fingerprint.");
          return;
        }
        applySecurityPayload(result.security || { login_mode: "signin" });
      } catch (_) {
        applyMode(state.login_mode || "biometric");
        alert("Network error. Try again.");
      }
      return;
    }

    if (!isArowinMobileBioContext()) {
      applyMode(state.login_mode || "signin");
      alert("Fingerprint works only on mobile browser or the mobile web app.");
      return;
    }

    if (!window.PublicKeyCredential) {
      applyMode(state.login_mode || "signin");
      alert("This device does not support fingerprint unlock.");
      return;
    }

    try {
      const userId = String(state.username || "arowin");
      const challenge = crypto.getRandomValues(new Uint8Array(32));
      const userIdBytes = new TextEncoder().encode(userId);
      const cred = await navigator.credentials.create({
        publicKey: {
          challenge,
          rp: { name: "Arowin Trading Portal", id: location.hostname },
          user: {
            id: userIdBytes,
            name: userId,
            displayName: userId,
          },
          pubKeyCredParams: [
            { type: "public-key", alg: -7 },
            { type: "public-key", alg: -257 },
          ],
          authenticatorSelection: {
            authenticatorAttachment: "platform",
            userVerification: "required",
            residentKey: "preferred",
          },
          // Prefer fingerprint / device unlock UI (not passkeys / security keys)
          hints: ["client-device"],
          timeout: 60000,
        },
      });
      if (!cred || !cred.rawId) {
        applyMode(state.login_mode || "signin");
        alert("Fingerprint setup was cancelled.");
        return;
      }
      const credentialId = b64url(cred.rawId);
      const publicKey = cred.response?.getPublicKey
        ? b64url(cred.response.getPublicKey())
        : "";
      const result = await post("enable_biometric", {
        credential_id: credentialId,
        public_key: publicKey,
      });
      if (!result.ok) {
        applyMode(state.login_mode || "signin");
        alert(result.message || "Could not enable fingerprint.");
        return;
      }
      applySecurityPayload(result.security || { login_mode: "biometric" });
    } catch (err) {
      applyMode(state.login_mode || "signin");
      alert(err && err.name === "NotAllowedError"
        ? "Fingerprint permission denied or cancelled."
        : "Could not enable fingerprint on this device.");
    }
  });

  applyMode(state.login_mode || (state.biometric_enabled ? "biometric" : state.two_fa_enabled ? "2fa" : "signin"));
})();
