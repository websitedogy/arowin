function toggleSidebar() {
  const s = document.getElementById("sidebar");
  const o = document.getElementById("overlay");
  if (!s || !o) return;
  s.classList.toggle("open");
  o.classList.toggle("show");
}

function closeSidebar() {
  if (window.innerWidth <= 900) {
    const s = document.getElementById("sidebar");
    const o = document.getElementById("overlay");
    if (s) s.classList.remove("open");
    if (o) o.classList.remove("show");
  }
}

function toggleDropdown(e) {
  e.stopPropagation();
  const dd = document.getElementById("profileDropdown");
  if (dd) dd.classList.toggle("show");
}

document.addEventListener("click", function () {
  const dd = document.getElementById("profileDropdown");
  if (dd) dd.classList.remove("show");
});

const profileDropdownEl = document.getElementById("profileDropdown");
if (profileDropdownEl) {
  profileDropdownEl.addEventListener("click", (e) => e.stopPropagation());
}

document.querySelectorAll(".nav-item").forEach((item) => {
  item.addEventListener("click", function () {
    document.querySelectorAll(".nav-item").forEach((i) => i.classList.remove("active"));
    this.classList.add("active");
  });
});

/* ── Account modals ─────────────────────────────────────── */
function accountApiUrl() {
  const dd = document.getElementById("profileDropdown");
  return (dd && dd.dataset.accountApi) || "";
}

function openAccountModal(kind) {
  const overlay = document.getElementById("acctOverlay");
  if (!overlay) return;
  document.getElementById("profileDropdown")?.classList.remove("show");

  ["Photo", "Password", "Operator", "History"].forEach((name) => {
    const panel = document.getElementById("acctPanel" + name);
    if (panel) panel.hidden = true;
  });

  const map = {
    photo: "Photo",
    password: "Password",
    operator: "Operator",
    history: "History",
  };
  const panel = document.getElementById("acctPanel" + (map[kind] || "Photo"));
  if (panel) panel.hidden = false;

  overlay.classList.remove("hidden");
  document.body.style.overflow = "hidden";

  if (kind === "history") {
    loadLoginHistory();
  }
  if (kind === "operator") {
    loadOperatorProfile();
  }
}

function closeAccountModal(e) {
  if (e && e.target && e.target !== e.currentTarget) {
    return;
  }
  const overlay = document.getElementById("acctOverlay");
  if (overlay) overlay.classList.add("hidden");
  document.body.style.overflow = "";
}

function closeAccountModalSoon() {
  setTimeout(() => closeAccountModal(), 700);
}

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeAccountModal();
});

function setAcctMsg(el, text, ok) {
  if (!el) return;
  el.hidden = !text;
  el.textContent = text || "";
  el.classList.toggle("is-ok", !!ok);
  el.classList.toggle("is-err", !ok && !!text);
}

function setBtnBusy(btn, busy, label) {
  if (!btn) return;
  btn.disabled = !!busy;
  if (label) btn.textContent = label;
}

async function parseAccountResponse(res) {
  const text = await res.text();
  let data = null;
  try {
    data = text ? JSON.parse(text) : null;
  } catch (_) {
    data = null;
  }
  if (!data || typeof data !== "object") {
    const snippet = (text || "").replace(/\s+/g, " ").trim().slice(0, 120);
    return {
      ok: false,
      message: snippet
        ? "Server error (" + res.status + "): " + snippet
        : "Empty server response (" + res.status + ").",
    };
  }
  if (!res.ok && !data.message) {
    data.message = "Request failed (" + res.status + ").";
  }
  return data;
}

async function postAccount(action, body) {
  const url = accountApiUrl();
  if (!url) throw new Error("Account API unavailable");
  const res = await fetch(url, {
    method: "POST",
    body,
    credentials: "same-origin",
    headers: { Accept: "application/json" },
  });
  return parseAccountResponse(res);
}

async function getAccount(action) {
  const url = accountApiUrl();
  if (!url) throw new Error("Account API unavailable");
  const res = await fetch(url + (url.includes("?") ? "&" : "?") + "action=" + encodeURIComponent(action), {
    method: "GET",
    credentials: "same-origin",
    headers: { Accept: "application/json" },
  });
  return parseAccountResponse(res);
}

function applyAvatarUrl(url) {
  if (!url) return;
  const headerBtn = document.getElementById("headerAvatarBtn");
  const ddAvatar = document.getElementById("ddAvatar");
  const preview = document.getElementById("acctPhotoPreview");

  function paint(container, imgId) {
    if (!container) return;
    let img = document.getElementById(imgId);
    if (!img) {
      container.innerHTML = "";
      img = document.createElement("img");
      img.id = imgId;
      img.className = "avatar-img";
      img.alt = "";
      container.appendChild(img);
    }
    img.src = url + (url.includes("?") ? "&" : "?") + "t=" + Date.now();
  }

  paint(headerBtn, "headerAvatarImg");
  paint(ddAvatar, "ddAvatarImg");
  if (preview) {
    preview.innerHTML = '<img src="' + url + "?t=" + Date.now() + '" alt="Current photo">';
  }
}

/* Photo upload */
const photoInput = document.getElementById("acctPhotoInput");
if (photoInput) {
  photoInput.addEventListener("change", () => {
    const file = photoInput.files && photoInput.files[0];
    const preview = document.getElementById("acctPhotoPreview");
    if (!file || !preview) return;
    const reader = new FileReader();
    reader.onload = () => {
      preview.innerHTML = '<img src="' + reader.result + '" alt="Preview">';
    };
    reader.readAsDataURL(file);
  });
}

const photoForm = document.getElementById("acctPhotoForm");
if (photoForm) {
  photoForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const msg = document.getElementById("acctPhotoMsg");
    const btn = document.getElementById("acctPhotoSubmit");
    const fd = new FormData(photoForm);
    fd.append("action", "upload_photo");
    setBtnBusy(btn, true, "Uploading…");
    setAcctMsg(msg, "", false);
    try {
      const data = await postAccount("upload_photo", fd);
      setAcctMsg(msg, data.message || (data.ok ? "Done." : "Failed."), !!data.ok);
      if (data.ok) {
        if (data.url) applyAvatarUrl(data.url);
        closeAccountModalSoon();
      }
    } catch (err) {
      setAcctMsg(msg, err.message || "Upload failed.", false);
    } finally {
      setBtnBusy(btn, false, "Upload");
    }
  });
}

/* Password */
const passwordForm = document.getElementById("acctPasswordForm");
if (passwordForm) {
  passwordForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const msg = document.getElementById("acctPasswordMsg");
    const btn = document.getElementById("acctPasswordSubmit");
    const fd = new FormData(passwordForm);
    fd.append("action", "change_password");
    setBtnBusy(btn, true, "Saving…");
    setAcctMsg(msg, "", false);
    try {
      const data = await postAccount("change_password", fd);
      setAcctMsg(msg, data.message || (data.ok ? "Done." : "Failed."), !!data.ok);
      if (data.ok) {
        passwordForm.reset();
        closeAccountModalSoon();
      }
    } catch (err) {
      setAcctMsg(msg, err.message || "Could not update password.", false);
    } finally {
      setBtnBusy(btn, false, "Save password");
    }
  });
}

/* Operator */
async function loadOperatorProfile() {
  try {
    const data = await getAccount("operator");
    if (!data.ok || !data.profile) return;
    const p = data.profile;
    const set = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.value = val || "";
    };
    set("acctFullName", p.full_name);
    set("acctEmail", p.email);
    set("acctPhone", p.phone);
    set("acctWallet", p.usdt_wallet);
    set("acctCountry", p.country);
  } catch (_) {
    /* keep server-rendered defaults */
  }
}

const operatorForm = document.getElementById("acctOperatorForm");
if (operatorForm) {
  operatorForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const msg = document.getElementById("acctOperatorMsg");
    const btn = document.getElementById("acctOperatorSubmit");
    const fd = new FormData(operatorForm);
    fd.append("action", "save_operator");
    setBtnBusy(btn, true, "Saving…");
    setAcctMsg(msg, "", false);
    try {
      const data = await postAccount("save_operator", fd);
      setAcctMsg(msg, data.message || (data.ok ? "Done." : "Failed."), !!data.ok);
      if (data.ok) {
        const name = (document.getElementById("acctFullName") || {}).value || "";
        const hello = document.getElementById("ddHello");
        if (hello && name) hello.textContent = "Hello, " + name + " 👋";
        closeAccountModalSoon();
      }
    } catch (err) {
      setAcctMsg(msg, err.message || "Could not save.", false);
    } finally {
      setBtnBusy(btn, false, "Save changes");
    }
  });
}

/* Login history */
async function loadLoginHistory() {
  const list = document.getElementById("acctHistoryList");
  const userLabel = document.getElementById("acctHistoryUser");
  if (!list) return;
  list.innerHTML = '<p class="acct-history-empty">Loading…</p>';
  try {
    const data = await getAccount("login_history");
    if (!data.ok) {
      list.innerHTML = '<p class="acct-history-empty">' + (data.message || "Could not load history.") + "</p>";
      return;
    }
    if (userLabel) {
      userLabel.textContent = data.username || "this ID";
    }
    const items = data.items || [];
    if (!items.length) {
      list.innerHTML = '<p class="acct-history-empty">No login history yet for this User ID. Real password / PIN sign-ins will appear here.</p>';
      return;
    }
    list.innerHTML = items
      .map(
        (row) =>
          '<div class="acct-history-row">' +
          '<div class="acct-history-when">' +
          escapeHtml(row.when_fmt || row.when || "—") +
          "</div>" +
          '<div class="acct-history-meta">' +
          '<span>' +
          escapeHtml(row.device || "Device") +
          "</span>" +
          "<span>IP " +
          escapeHtml(row.ip || "—") +
          "</span>" +
          "</div></div>"
      )
      .join("");
  } catch (err) {
    list.innerHTML = '<p class="acct-history-empty">' + escapeHtml(err.message || "Failed to load.") + "</p>";
  }
}

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

/* ── Deposit modal (QR / unique address flow) ───────── */
let arowinDepositPollTimer = null;
let arowinDepositOrderId = "";

function arowinStopDepositPoll() {
  if (arowinDepositPollTimer) {
    clearInterval(arowinDepositPollTimer);
    arowinDepositPollTimer = null;
  }
}

function arowinShowDepositForm() {
  const formStep = document.getElementById("depositStepForm");
  const payStep = document.getElementById("depositStepPay");
  const title = document.getElementById("depositTitle");
  const eyebrow = document.getElementById("depositEyebrow");
  if (formStep) formStep.hidden = false;
  if (payStep) payStep.hidden = true;
  if (title) title.textContent = window.AROWIN_DEPOSIT_MODE === "nowpayments" ? "Generate address" : "Deposit USDT";
  if (eyebrow) eyebrow.textContent = window.AROWIN_DEPOSIT_MODE === "nowpayments" ? "Inbound deposit" : "Wallet";
  arowinStopDepositPoll();
  arowinDepositOrderId = "";
}

function arowinShowDepositPay(data) {
  const formStep = document.getElementById("depositStepForm");
  const payStep = document.getElementById("depositStepPay");
  const title = document.getElementById("depositTitle");
  const eyebrow = document.getElementById("depositEyebrow");
  if (formStep) formStep.hidden = true;
  if (payStep) payStep.hidden = false;
  if (title) title.textContent = "Payment initialized";
  if (eyebrow) eyebrow.textContent = "Inbound deposit";

  const amountEl = document.getElementById("depositPayAmount");
  const addrEl = document.getElementById("depositPayAddress");
  const qrEl = document.getElementById("depositQrImg");
  const statusEl = document.getElementById("depositPayStatus");
  const msgEl = document.getElementById("depositPayMsg");

  const usd = Number(data.amount_usd);
  if (amountEl) {
    amountEl.textContent = Number.isFinite(usd) && usd > 0
      ? "$" + usd.toFixed(2) + " USDT"
      : "$0.00 USDT";
  }
  if (addrEl) addrEl.value = data.pay_address || "";
  if (qrEl) {
    qrEl.src = data.qr_url || "";
    qrEl.alt = "QR for " + (data.pay_address || "deposit");
  }
  if (statusEl) statusEl.textContent = "Payment initialized";
  if (msgEl) {
    msgEl.hidden = true;
    msgEl.textContent = "";
  }

  arowinDepositOrderId = data.order_id || "";
  arowinStopDepositPoll();
  if (arowinDepositOrderId && window.AROWIN_DEPOSIT_STATUS_API) {
    arowinDepositPollTimer = setInterval(() => {
      arowinCheckDepositStatus(true);
    }, 4000);
  }
}

async function arowinCheckDepositStatus(silent) {
  const api = window.AROWIN_DEPOSIT_STATUS_API || "";
  const msgEl = document.getElementById("depositPayMsg");
  const statusEl = document.getElementById("depositPayStatus");
  const btn = document.getElementById("depositCheckBtn");
  if (!api || !arowinDepositOrderId) return;

  if (btn && !silent) {
    btn.disabled = true;
    btn.textContent = "Checking…";
  }

  try {
    const res = await fetch(api + "?order_id=" + encodeURIComponent(arowinDepositOrderId), {
      method: "GET",
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.message || "Status check failed.");

    if (statusEl) {
      statusEl.textContent = data.credited
        ? "Payment received — wallet updated"
        : "Status: " + String(data.status || "waiting");
    }
    if (data.pay_address) {
      const addrEl = document.getElementById("depositPayAddress");
      if (addrEl && !addrEl.value) addrEl.value = data.pay_address;
    }
    if (data.qr_url) {
      const qrEl = document.getElementById("depositQrImg");
      if (qrEl && !qrEl.getAttribute("src")) qrEl.src = data.qr_url;
    }
    if (typeof data.balance !== "undefined") {
      updateWalletLabels(data.balance);
    }
    if (data.credited) {
      arowinStopDepositPoll();
      if (msgEl) {
        msgEl.hidden = false;
        msgEl.className = "dep-msg ok";
        msgEl.textContent = "Wallet credited successfully.";
      }
    } else if (!silent && msgEl) {
      msgEl.hidden = false;
      msgEl.className = "dep-msg";
      msgEl.textContent = data.message || "Waiting for your payment to be detected…";
    }
  } catch (err) {
    if (!silent && msgEl) {
      msgEl.hidden = false;
      msgEl.className = "dep-msg err";
      msgEl.textContent = err.message || "Status check failed.";
    }
  } finally {
    if (btn && !silent) {
      btn.disabled = false;
      btn.textContent = "Check payment status";
    }
  }
}

function updateWalletLabels(balance) {
  const n = Number(balance);
  if (Number.isNaN(n)) return;
  const amount =
    "$" +
    n.toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  const formatted = amount + " USDT";

  const modalBal = document.getElementById("depositBalanceLabel");
  if (modalBal) modalBal.textContent = formatted;

  const transferBal = document.getElementById("transferBalanceLabel");
  if (transferBal) transferBal.textContent = formatted;

  const navWallet = document.getElementById("navWalletValue");
  if (navWallet) navWallet.textContent = amount;

  document.querySelectorAll(".chip-label").forEach((label) => {
    const text = label.textContent.trim().toLowerCase();
    if (text !== "wallet" && text !== "master wallet") return;
    const value =
      label.parentElement && label.parentElement.querySelector(".chip-value");
    if (value) value.textContent = amount;
  });
}

/* ── Transfer modal ── */
let arowinTransferLookupTimer = null;
let arowinTransferSelectedId = "";

function openTransferModal() {
  const overlay = document.getElementById("transferOverlay");
  if (!overlay) return;
  overlay.classList.remove("hidden");
  overlay.setAttribute("aria-hidden", "false");
  document.body.style.overflow = "hidden";
  arowinResetTransferForm();
  const input = document.getElementById("transferToUser");
  if (input) {
    input.focus();
  }
}

function closeTransferModal(event) {
  if (event && event.target !== event.currentTarget && event.type === "click") {
    return;
  }
  const overlay = document.getElementById("transferOverlay");
  if (!overlay) return;
  overlay.classList.add("hidden");
  overlay.setAttribute("aria-hidden", "true");
  document.body.style.overflow = "";
  if (arowinTransferLookupTimer) {
    clearTimeout(arowinTransferLookupTimer);
    arowinTransferLookupTimer = null;
  }
}

function arowinResetTransferForm() {
  arowinTransferSelectedId = "";
  const form = document.getElementById("transferForm");
  if (form) form.reset();
  const msg = document.getElementById("transferMsg");
  if (msg) {
    msg.hidden = true;
    msg.textContent = "";
    msg.className = "dep-msg";
  }
  const lookupMsg = document.getElementById("transferLookupMsg");
  if (lookupMsg) {
    lookupMsg.hidden = true;
    lookupMsg.textContent = "";
  }
  const preview = document.getElementById("transferPreview");
  if (preview) preview.classList.add("hidden");
  const submit = document.getElementById("transferSubmit");
  if (submit) {
    submit.disabled = true;
    submit.textContent = "Send transfer";
  }
}

function arowinShowTransferPreview(id, name, ok) {
  const preview = document.getElementById("transferPreview");
  const nameEl = document.getElementById("transferPreviewName");
  const idEl = document.getElementById("transferPreviewId");
  const initialEl = document.getElementById("transferPreviewInitial");
  const badge = document.getElementById("transferPreviewBadge");
  const submit = document.getElementById("transferSubmit");
  const lookupMsg = document.getElementById("transferLookupMsg");

  if (lookupMsg) {
    lookupMsg.hidden = true;
    lookupMsg.textContent = "";
  }

  if (!preview || !ok) {
    arowinTransferSelectedId = "";
    if (preview) preview.classList.add("hidden");
    if (submit) submit.disabled = true;
    return;
  }

  arowinTransferSelectedId = String(id || "").toUpperCase();
  preview.classList.remove("hidden");
  if (nameEl) nameEl.textContent = name || "Member";
  if (idEl) idEl.textContent = arowinTransferSelectedId;
  if (initialEl) {
    initialEl.textContent = (name || id || "?").trim().charAt(0).toUpperCase();
  }
  if (badge) badge.textContent = "Selected";
  if (submit) submit.disabled = false;
}

async function arowinLookupTransferRecipient(rawId) {
  const api = window.AROWIN_TRANSFER_API || "";
  const lookupMsg = document.getElementById("transferLookupMsg");
  const id = String(rawId || "")
    .trim()
    .toUpperCase()
    .replace(/\s+/g, "");

  if (!api || id.length < 4) {
    arowinShowTransferPreview("", "", false);
    return;
  }

  if (lookupMsg) {
    lookupMsg.hidden = false;
    lookupMsg.textContent = "Looking up…";
    lookupMsg.className = "xfer-lookup-msg";
  }

  try {
    const res = await fetch(
      api + "?lookup=1&user_id=" + encodeURIComponent(id),
      { headers: { Accept: "application/json" }, credentials: "same-origin" }
    );
    const data = await res.json();
    if (!data.ok || data.status !== "valid") {
      arowinShowTransferPreview("", "", false);
      if (lookupMsg) {
        lookupMsg.hidden = false;
        lookupMsg.className = "xfer-lookup-msg err";
        lookupMsg.textContent = data.message || "User ID not found.";
      }
      return;
    }
    arowinShowTransferPreview(data.id, data.name, true);
  } catch (_) {
    arowinShowTransferPreview("", "", false);
    if (lookupMsg) {
      lookupMsg.hidden = false;
      lookupMsg.className = "xfer-lookup-msg err";
      lookupMsg.textContent = "Lookup failed. Try again.";
    }
  }
}

function openDepositModal() {
  const overlay = document.getElementById("depositOverlay");
  if (!overlay) return;
  arowinShowDepositForm();
  overlay.classList.remove("hidden");
  overlay.setAttribute("aria-hidden", "false");
  document.body.style.overflow = "hidden";
  const msg = document.getElementById("depositMsg");
  if (msg) {
    msg.hidden = true;
    msg.textContent = "";
    msg.className = "dep-msg";
  }
  const input = document.getElementById("depositAmount");
  if (input) {
    input.focus();
    input.select();
  }
}

/* ── Withdrawal modal ── */
function openWithdrawalModal() {
  const overlay = document.getElementById("withdrawalOverlay");
  if (!overlay) return;
  overlay.classList.remove("hidden");
  overlay.setAttribute("aria-hidden", "false");
  document.body.style.overflow = "hidden";
  arowinResetWithdrawalForm();
  arowinRefreshWithdrawalSettings();
  const input = document.getElementById("withdrawalAmount");
  if (input) {
    input.focus();
  }
}

function closeWithdrawalModal(event) {
  if (event && event.target !== event.currentTarget && event.type === "click") {
    return;
  }
  const overlay = document.getElementById("withdrawalOverlay");
  if (!overlay) return;
  overlay.classList.add("hidden");
  overlay.setAttribute("aria-hidden", "true");
  arowinResetWithdrawalForm();
  document.body.style.overflow = "";
}

function arowinResetWithdrawalForm() {
  arowinResetWithdrawalRecaptcha();
  window.AROWIN_WITHDRAWAL_CONFIRMATION = null;

  const form = document.getElementById("withdrawalForm");
  if (form) {
    const addr = document.getElementById("withdrawalAddress");
    const selected = addr ? addr.value : "";
    form.reset();
    if (addr && selected) addr.value = selected;
  }
  const msg = document.getElementById("withdrawalMsg");
  if (msg) {
    msg.hidden = true;
    msg.textContent = "";
    msg.className = "dep-msg";
  }
  const submit = document.getElementById("withdrawalSubmit");
  const otpStep = document.getElementById("withdrawalOtpStep");
  if (submit) {
    submit.disabled = false;
    submit.textContent = "Send OTP";
  }
  if (otpStep) {
    otpStep.hidden = true;
  }
}

function arowinResetWithdrawalRecaptcha() {
  if (window.AROWIN_FIREBASE_RECAPTCHA && typeof window.AROWIN_FIREBASE_RECAPTCHA.clear === "function") {
    try {
      window.AROWIN_FIREBASE_RECAPTCHA.clear();
    } catch (_) {
      /* The verifier may already be detached after an expired challenge. */
    }
  }
  window.AROWIN_FIREBASE_RECAPTCHA = null;
  window.AROWIN_FIREBASE_RECAPTCHA_RENDERED = false;
  const container = document.getElementById("withdrawalRecaptcha");
  if (container) container.replaceChildren();
}

async function arowinRefreshWithdrawalSettings() {
  const api = window.AROWIN_WITHDRAWAL_API || "";
  if (!api) return;
  try {
    const res = await fetch(api, {
      headers: { Accept: "application/json" },
      credentials: "same-origin",
    });
    const data = await res.json();
    if (!data.ok) return;
    if (typeof data.balance === "number") {
      updateWalletLabels(data.balance);
    }
    const select = document.getElementById("withdrawalAddress");
    if (select && Array.isArray(data.addresses) && data.addresses.length) {
      const prev = select.value;
      select.innerHTML = data.addresses
        .map(function (a) {
          const network = String(a.currency || "").toLowerCase().includes("trc") ? "TRC20" : "BEP20";
          const label = (a.label || "Wallet") + " · " + network + " — " + String(a.address || "").slice(0, 8) + "…" + String(a.address || "").slice(-6);
          const sel = String(a.id) === String(prev) || (!prev && Number(a.is_default)) ? " selected" : "";
          return '<option value="' + a.id + '"' + sel + ">" + label.replace(/</g, "&lt;") + "</option>";
        })
        .join("");
    }
  } catch (_) {
    /* keep server-rendered options */
  }
}

function closeDepositModal(event) {
  if (event && event.target !== event.currentTarget && event.type === "click") {
    return;
  }
  const overlay = document.getElementById("depositOverlay");
  if (!overlay) return;
  overlay.classList.add("hidden");
  overlay.setAttribute("aria-hidden", "true");
  document.body.style.overflow = "";
  arowinStopDepositPoll();
}

function updateWalletLabels(balance) {
  const n = Number(balance);
  if (Number.isNaN(n)) return;
  const amount =
    "$" +
    n.toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  const formatted = amount + " USDT";

  const modalBal = document.getElementById("depositBalanceLabel");
  if (modalBal) modalBal.textContent = formatted;

  const transferBal = document.getElementById("transferBalanceLabel");
  if (transferBal) transferBal.textContent = formatted;

  const withdrawalBal = document.getElementById("withdrawalBalanceLabel");
  if (withdrawalBal) withdrawalBal.textContent = formatted;

  const navWallet = document.getElementById("navWalletValue");
  if (navWallet) navWallet.textContent = amount;

  document.querySelectorAll(".chip-label").forEach((label) => {
    const text = label.textContent.trim().toLowerCase();
    if (text !== "wallet" && text !== "master wallet") return;
    const value =
      label.parentElement && label.parentElement.querySelector(".chip-value");
    if (value) value.textContent = amount;
  });
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll("[data-dep-quick]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const input = document.getElementById("depositAmount");
      if (!input) return;
      input.value = btn.getAttribute("data-dep-quick") || "";
      input.focus();
    });
  });

  document.querySelectorAll("[data-pay-currency]").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll("[data-pay-currency]").forEach((b) => {
        b.classList.remove("active");
        b.setAttribute("aria-pressed", "false");
      });
      btn.classList.add("active");
      btn.setAttribute("aria-pressed", "true");
      const hidden = document.getElementById("depositPayCurrency");
      if (hidden) hidden.value = btn.getAttribute("data-pay-currency") || "";
    });
  });

  const copyBtn = document.getElementById("depositCopyBtn");
  if (copyBtn) {
    copyBtn.addEventListener("click", async () => {
      const addrEl = document.getElementById("depositPayAddress");
      if (!addrEl || !addrEl.value) return;
      try {
        await navigator.clipboard.writeText(addrEl.value);
        copyBtn.textContent = "Copied";
        setTimeout(() => {
          copyBtn.textContent = "Copy";
        }, 1200);
      } catch (_) {
        addrEl.select();
        document.execCommand("copy");
      }
    });
  }

  const checkBtn = document.getElementById("depositCheckBtn");
  if (checkBtn) {
    checkBtn.addEventListener("click", () => arowinCheckDepositStatus(false));
  }

  const cancelBtn = document.getElementById("depositCancelBtn");
  if (cancelBtn) {
    cancelBtn.addEventListener("click", () => closeDepositModal());
  }

  const form = document.getElementById("depositForm");
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const api = window.AROWIN_DEPOSIT_API || "";
    const mode = window.AROWIN_DEPOSIT_MODE || "test";
    const amountEl = document.getElementById("depositAmount");
    const noteEl = document.getElementById("depositNote");
    const msg = document.getElementById("depositMsg");
    const submit = document.getElementById("depositSubmit");
    const csrf = form.querySelector('input[name="csrf"]');
    const payCurrencyEl = document.getElementById("depositPayCurrency");
    if (!amountEl || !submit || !api) return;

    const amount = Number(amountEl.value);
    if (!amount || amount < 1) {
      if (msg) {
        msg.hidden = false;
        msg.className = "dep-msg err";
        msg.textContent = "Enter at least 1 USD.";
      }
      return;
    }

    submit.disabled = true;
    submit.textContent = mode === "nowpayments" ? "Generating address…" : "Processing…";
    if (msg) {
      msg.hidden = true;
      msg.textContent = "";
    }

    try {
      const res = await fetch(api, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({
          csrf: csrf ? csrf.value : "",
          amount,
          note: noteEl ? noteEl.value : "",
          pay_currency: payCurrencyEl ? payCurrencyEl.value : "",
        }),
      });
      const raw = await res.text();
      let data = null;
      try {
        data = JSON.parse(raw);
      } catch (parseErr) {
        throw new Error(
          "Deposit API did not return JSON. Check URL " + api + " (HTTP " + res.status + ")."
        );
      }
      if (!data.ok) {
        throw new Error(data.message || "Deposit failed.");
      }

      if (mode === "nowpayments") {
        if (data.pay_address) {
          arowinShowDepositPay(data);
        } else if (data.redirect_url) {
          window.location.href = data.redirect_url;
          return;
        } else {
          throw new Error("No deposit address returned. Try another network.");
        }
      } else {
        if (typeof data.balance !== "undefined") {
          updateWalletLabels(data.balance);
        }
        if (msg) {
          msg.hidden = false;
          msg.className = "dep-msg ok";
          msg.textContent = data.message || "Deposit added to wallet.";
        }
        amountEl.value = "";
        setTimeout(() => closeDepositModal(), 900);
      }
    } catch (err) {
      if (msg) {
        msg.hidden = false;
        msg.className = "dep-msg err";
        msg.textContent = err.message || "Deposit failed.";
      }
    } finally {
      submit.disabled = false;
      submit.textContent =
        mode === "nowpayments" ? "Generate deposit address" : "Add to wallet";
    }
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeDepositModal();
      closeTransferModal();
      closeWithdrawalModal();
    }
  });

  if (window.location.pathname.includes("/deposit") || window.location.hash === "#deposit") {
    openDepositModal();
  }

  if (window.location.hash === "#transfer") {
    openTransferModal();
  }

  if (window.location.hash === "#withdrawal") {
    openWithdrawalModal();
  }

  // Withdrawal modal
  (function initWithdrawalModal() {
    const form = document.getElementById("withdrawalForm");
    if (!form) return;

    document.querySelectorAll("[data-wd-quick]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const input = document.getElementById("withdrawalAmount");
        if (!input) return;
        input.value = btn.getAttribute("data-wd-quick") || "";
        input.focus();
        arowinUpdateWithdrawalFeeLine();
      });
    });

    const maxBtn = document.querySelector("[data-wd-all]");
    if (maxBtn) {
      maxBtn.addEventListener("click", () => {
        const label = document.getElementById("withdrawalBalanceLabel");
        const input = document.getElementById("withdrawalAmount");
        if (!label || !input) return;
        const m = String(label.textContent || "").replace(/[^0-9.]/g, "");
        const n = parseFloat(m);
        if (!Number.isNaN(n) && n > 0) {
          input.value = n.toFixed(2);
          input.focus();
          arowinUpdateWithdrawalFeeLine();
        }
      });
    }

    function arowinUpdateWithdrawalFeeLine() {
      const line = document.getElementById("withdrawalFeeLine");
      const input = document.getElementById("withdrawalAmount");
      if (!line) return;
      const gross = parseFloat(input ? input.value : "0");
      if (!Number.isFinite(gross) || gross < 10) {
        line.textContent = "Min $10. 10% fee — you receive 90% on-chain.";
        return;
      }
      const net = Math.round(gross * 0.9 * 100) / 100;
      const fee = Math.round((gross - net) * 100) / 100;
      line.textContent =
        "Wallet cut $" +
        gross.toFixed(2) +
        " · 10% fee $" +
        fee.toFixed(2) +
        " · you receive $" +
        net.toFixed(2);
    }

    const amountInput = document.getElementById("withdrawalAmount");
    if (amountInput) {
      amountInput.addEventListener("input", arowinUpdateWithdrawalFeeLine);
    }

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const api = window.AROWIN_WITHDRAWAL_API || "";
      const msg = document.getElementById("withdrawalMsg");
      const submit = document.getElementById("withdrawalSubmit");
      const amountEl = document.getElementById("withdrawalAmount");
      const addrEl = document.getElementById("withdrawalAddress");
      const otpStep = document.getElementById("withdrawalOtpStep");
      const otpEl = document.getElementById("withdrawalOtp");
      if (!api || !submit) return;

      const amount = parseFloat(amountEl ? amountEl.value : "0");
      const addressId = parseInt(addrEl ? addrEl.value : "0", 10);
      const otp = otpEl ? String(otpEl.value || "").trim() : "";

      if (!(amount >= 10) || !addressId) {
        if (msg) {
          msg.hidden = false;
          msg.className = "dep-msg err";
          msg.textContent = !(amount >= 10)
            ? "Minimum withdrawal is $10.00 USDT."
            : "Select a withdrawal address.";
        }
        return;
      }

      const balLabel = document.getElementById("withdrawalBalanceLabel");
      const balRaw = balLabel
        ? String(balLabel.textContent || "").replace(/[^0-9.]/g, "")
        : "";
      const walletBal = parseFloat(balRaw);
      if (!Number.isNaN(walletBal) && amount > walletBal + 0.00001) {
        if (msg) {
          msg.hidden = false;
          msg.className = "dep-msg err";
          msg.textContent =
            "Limit crossed. You entered $" +
            amount.toFixed(2) +
            " but Master Wallet has only $" +
            walletBal.toFixed(2) +
            " USDT.";
        }
        return;
      }

      submit.disabled = true;
      submit.textContent = otp ? "Verifying OTP…" : "Sending OTP…";
      if (msg) {
        msg.hidden = true;
        msg.textContent = "";
        msg.className = "dep-msg";
      }

      try {
        if (!otp) {
          const config = window.AROWIN_FIREBASE_CONFIG || {};
          if (!config.apiKey || !window.AROWIN_WITHDRAWAL_PHONE) {
            throw new Error("Firebase OTP is not configured for this account.");
          }
          if (!window.firebase) throw new Error("Firebase could not load. Refresh and try again.");
          if (!window.AROWIN_FIREBASE_APP) {
            window.AROWIN_FIREBASE_APP = firebase.initializeApp(config);
            window.AROWIN_FIREBASE_AUTH = firebase.auth();
          }
          if (!window.AROWIN_FIREBASE_RECAPTCHA) {
            window.AROWIN_FIREBASE_RECAPTCHA = new firebase.auth.RecaptchaVerifier(
              "withdrawalRecaptcha",
              { size: "invisible" }
            );
          }
          if (window.AROWIN_FIREBASE_RECAPTCHA_RENDERED !== true) {
            await window.AROWIN_FIREBASE_RECAPTCHA.render();
            window.AROWIN_FIREBASE_RECAPTCHA_RENDERED = true;
          }
          const phone = "+91" + String(window.AROWIN_WITHDRAWAL_PHONE).slice(-10);
          window.AROWIN_WITHDRAWAL_CONFIRMATION = await window.AROWIN_FIREBASE_AUTH.signInWithPhoneNumber(
            phone,
            window.AROWIN_FIREBASE_RECAPTCHA
          );
          if (otpStep) otpStep.hidden = false;
          if (otpEl) otpEl.focus();
          if (msg) {
            msg.hidden = false;
            msg.className = "dep-msg ok";
            msg.textContent = "OTP sent to your registered mobile number. Enter it to continue.";
          }
          submit.textContent = "Verify OTP & Withdraw";
          return;
        }
        if (!/^\d{6}$/.test(otp) || !window.AROWIN_WITHDRAWAL_CONFIRMATION) {
          throw new Error("Enter the 6-digit OTP sent to your mobile.");
        }
        const credential = await window.AROWIN_WITHDRAWAL_CONFIRMATION.confirm(otp);
        const firebaseToken = await credential.user.getIdToken(true);
        const res = await fetch(api, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          credentials: "same-origin",
          body: JSON.stringify({
            action: "withdraw",
            csrf: window.AROWIN_WITHDRAWAL_CSRF || "",
            amount: amount,
            address_id: addressId,
            firebase_token: firebaseToken,
          }),
        });
        const data = await res.json();
        if (typeof data.balance === "number") {
          updateWalletLabels(data.balance);
        }
        if (msg) {
          msg.hidden = false;
          if (data.ok && (data.queued || data.status === "queued")) {
            msg.className = "dep-msg warn";
          } else {
            msg.className = data.ok ? "dep-msg ok" : "dep-msg err";
          }
          msg.textContent =
            data.message ||
            (data.ok ? "Withdrawal submitted." : "Withdrawal failed.");
        }
        if (data.ok && otpEl) otpEl.value = "";
        if (data.ok && amountEl) {
          amountEl.value = "";
        }
        if (data.ok) {
          window.AROWIN_WITHDRAWAL_CONFIRMATION = null;
          if (otpStep) otpStep.hidden = true;
        }
      } catch (err) {
        arowinResetWithdrawalRecaptcha();
        window.AROWIN_WITHDRAWAL_CONFIRMATION = null;
        if (otpStep) otpStep.hidden = true;
        if (otpEl) otpEl.value = "";
        if (msg) {
          msg.hidden = false;
          msg.className = "dep-msg err";
          if (err && err.code === "auth/too-many-requests") {
            msg.textContent = "Firebase temporarily blocked OTP requests from this device. Please wait and try again later.";
          } else if (err && err.code === "auth/captcha-check-failed") {
            msg.textContent = "OTP is not enabled for this website domain. Add the live domain to Firebase Authentication > Settings > Authorized domains, then try again.";
          } else {
            msg.textContent = err.message || "Network error. Try again.";
          }
        }
      } finally {
        submit.disabled = false;
        submit.textContent = window.AROWIN_WITHDRAWAL_CONFIRMATION
          ? "Verify OTP & Withdraw"
          : "Send OTP";
      }
    });
  })();

  // Withdrawal permission popup for Mother ID transfers
  function showWithdrawalPermissionPopup(targetUser, targetName) {
    const overlay = document.getElementById("withdrawalPermOverlay");
    if (!overlay) {
      // Create overlay if it doesn't exist
      const overlayDiv = document.createElement("div");
      overlayDiv.id = "withdrawalPermOverlay";
      overlayDiv.className = "dep-overlay";
      overlayDiv.setAttribute("aria-hidden", "true");
      overlayDiv.innerHTML = `
        <div class="dep-sheet" role="dialog" aria-modal="true">
          <div class="dep-head">
            <div>
              <p class="dep-eyebrow">Withdrawal Address Permission</p>
              <h2>Set Permission for ${targetName}</h2>
            </div>
            <button type="button" class="dep-close" onclick="closeWithdrawalPermissionPopup()" aria-label="Close">&times;</button>
          </div>
          <div class="dep-body" style="padding: 20px;">
            <p style="margin-bottom: 16px; color: var(--text-muted);">
              Do you want to allow <strong>${targetUser}</strong> to add withdrawal addresses in their Operator Configuration?
            </p>
            <div style="display: flex; gap: 12px; flex-direction: column;">
              <button type="button" class="dep-submit" id="wdPermEnable" style="background: #10b981;">
                Enable - Allow adding withdrawal addresses
              </button>
              <button type="button" class="dep-submit" id="wdPermDisable" style="background: #ef4444;">
                Disable - Cannot add withdrawal addresses
              </button>
            </div>
            <p id="wdPermMsg" class="dep-msg" hidden></p>
          </div>
        </div>
      `;
      document.body.appendChild(overlayDiv);

      // Add event listeners
      document.getElementById("wdPermEnable").addEventListener("click", () => {
        setWithdrawalPermission(targetUser, true);
      });
      document.getElementById("wdPermDisable").addEventListener("click", () => {
        setWithdrawalPermission(targetUser, false);
      });
      overlayDiv.addEventListener("click", (e) => {
        if (e.target === overlayDiv) {
          closeWithdrawalPermissionPopup();
          closeTransferModal();
        }
      });
    }

    overlay.classList.remove("hidden");
    overlay.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function closeWithdrawalPermissionPopup() {
    const overlay = document.getElementById("withdrawalPermOverlay");
    if (overlay) {
      overlay.classList.add("hidden");
      overlay.setAttribute("aria-hidden", "true");
      document.body.style.overflow = "";
    }
  }

  async function setWithdrawalPermission(targetUser, allow) {
    const api = window.AROWIN_TRANSFER_API || "";
    const msg = document.getElementById("wdPermMsg");
    const enableBtn = document.getElementById("wdPermEnable");
    const disableBtn = document.getElementById("wdPermDisable");

    if (!api) return;

    if (enableBtn) enableBtn.disabled = true;
    if (disableBtn) disableBtn.disabled = true;

    if (msg) {
      msg.hidden = false;
      msg.className = "dep-msg";
      msg.textContent = "Updating permission...";
    }

    try {
      const res = await fetch(api, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        credentials: "same-origin",
        body: JSON.stringify({
          action: "set_withdrawal_permission",
          csrf: document.querySelector('#transferForm input[name="csrf"]')?.value || "",
          target_user: targetUser,
          allow: allow,
        }),
      });
      const data = await res.json();
      if (msg) {
        msg.className = data.ok ? "dep-msg ok" : "dep-msg err";
        msg.textContent = data.message || (data.ok ? "Permission updated." : "Failed to update permission.");
      }
      if (data.ok) {
        setTimeout(() => {
          closeWithdrawalPermissionPopup();
          closeTransferModal();
        }, 1500);
      }
    } catch (err) {
      if (msg) {
        msg.className = "dep-msg err";
        msg.textContent = err.message || "Network error. Try again.";
      }
    } finally {
      if (enableBtn) enableBtn.disabled = false;
      if (disableBtn) disableBtn.disabled = false;
    }
  }

  // Transfer modal: live ID → name lookup + submit
  (function initTransferModal() {
    const toInput = document.getElementById("transferToUser");
    const form = document.getElementById("transferForm");
    if (!toInput || !form) return;

    toInput.addEventListener("input", () => {
      const val = toInput.value.trim().toUpperCase();
      toInput.value = val;
      arowinTransferSelectedId = "";
      const submit = document.getElementById("transferSubmit");
      if (submit) submit.disabled = true;
      const preview = document.getElementById("transferPreview");
      if (preview) preview.classList.add("hidden");
      if (arowinTransferLookupTimer) clearTimeout(arowinTransferLookupTimer);
      arowinTransferLookupTimer = setTimeout(() => {
        arowinLookupTransferRecipient(val);
      }, 350);
    });

    toInput.addEventListener("blur", () => {
      arowinLookupTransferRecipient(toInput.value);
    });

    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const api = window.AROWIN_TRANSFER_API || "";
      const amountEl = document.getElementById("transferAmount");
      const noteEl = document.getElementById("transferNote");
      const msg = document.getElementById("transferMsg");
      const submit = document.getElementById("transferSubmit");
      const csrf = form.querySelector('input[name="csrf"]');
      if (!amountEl || !submit || !api) return;

      const toUser = arowinTransferSelectedId || toInput.value.trim().toUpperCase();
      const amount = Number(amountEl.value);

      if (!toUser) {
        if (msg) {
          msg.hidden = false;
          msg.className = "dep-msg err";
          msg.textContent = "Enter a valid recipient User ID.";
        }
        return;
      }
      if (!amount || amount < 1) {
        if (msg) {
          msg.hidden = false;
          msg.className = "dep-msg err";
          msg.textContent = "Enter at least 1 USDT.";
        }
        return;
      }
      if (!arowinTransferSelectedId) {
        await arowinLookupTransferRecipient(toUser);
        if (!arowinTransferSelectedId) {
          if (msg) {
            msg.hidden = false;
            msg.className = "dep-msg err";
            msg.textContent = "Select a valid recipient first.";
          }
          return;
        }
      }

      // Check if sender has withdrawal address permission disabled
      try {
        const basePath = window.AROWIN_TRANSFER_API ? window.AROWIN_TRANSFER_API.replace('/transfer.php', '') : '/dashboard/api';
        const withdrawalRes = await fetch(basePath + '/withdrawal.php', {
          method: "GET",
          credentials: "same-origin",
        });
        const withdrawalData = await withdrawalRes.json();
        if (withdrawalData && withdrawalData.can_add_address === false) {
          alert("Please contact admin to enable withdrawal address permission.");
          return;
        }
      } catch (err) {
        // If check fails, allow transfer to proceed
        console.error("Failed to check withdrawal permission:", err);
      }

      submit.disabled = true;
      submit.textContent = "Sending…";
      if (msg) {
        msg.hidden = true;
        msg.textContent = "";
      }

      try {
        const res = await fetch(api, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          credentials: "same-origin",
          body: JSON.stringify({
            csrf: csrf ? csrf.value : "",
            to_user: arowinTransferSelectedId,
            amount,
            note: noteEl ? noteEl.value : "",
          }),
        });
        const raw = await res.text();
        let data = null;
        try {
          data = JSON.parse(raw);
        } catch (_) {
          throw new Error(
            "Transfer API did not return JSON. Check URL " +
              api +
              " (HTTP " +
              res.status +
              ")."
          );
        }
        if (!data.ok) {
          throw new Error(data.message || "Transfer failed.");
        }
        if (typeof data.balance !== "undefined") {
          updateWalletLabels(data.balance);
        }
        if (msg) {
          msg.hidden = false;
          msg.className = "dep-msg ok";
          msg.textContent = data.message || "Transfer sent.";
        }
        amountEl.value = "";
        if (noteEl) noteEl.value = "";

        // If sender is Mother ID, show popup to set withdrawal address permission
        if (data.sender_is_mother) {
          setTimeout(() => {
            showWithdrawalPermissionPopup(toUser, data.to_name || toUser);
          }, 500);
        } else {
          setTimeout(() => closeTransferModal(), 1000);
        }
      } catch (err) {
        if (msg) {
          msg.hidden = false;
          msg.className = "dep-msg err";
          msg.textContent = err.message || "Transfer failed.";
        }
        submit.disabled = !arowinTransferSelectedId;
        submit.textContent = "Send transfer";
        return;
      }
      submit.disabled = !arowinTransferSelectedId;
      submit.textContent = "Send transfer";
    });
  })();

  try {
    const params = new URLSearchParams(window.location.search);
    const depositState = params.get("deposit");
    if (depositState === "success" || depositState === "cancelled" || depositState === "error") {
      openDepositModal();
      const msgEl = document.getElementById("depositMsg");
      if (msgEl) {
        msgEl.hidden = false;
        msgEl.className = depositState === "success" ? "dep-msg ok" : "dep-msg err";
        if (depositState === "success") {
          msgEl.textContent =
            "Payment submitted. Your wallet will be credited as soon as the payment is detected.";
        } else if (depositState === "cancelled") {
          msgEl.textContent = "Payment cancelled. No funds were added.";
        } else {
          msgEl.textContent = "Could not start deposit. Please try again.";
        }
      }
    }
  } catch (_) {
    /* ignore */
  }

  // Income / business policy popups
  (function initDashboardPolicies() {
    const overlay = document.getElementById("dyPolicyOverlay");
    if (!overlay) return;

    const titleEl = document.getElementById("dyPolicyTitle");
    const bodyEl = document.getElementById("dyPolicyBody");
    const closeBtn = document.getElementById("dyPolicyClose");
    const okBtn = document.getElementById("dyPolicyOk");
    const shareBtn = document.getElementById("dyPolicyShare");
    let shareText = "";

    const policies = {
      earnings: {
        title: "Earnings Policy",
        lead: "What counts in your Earnings total",
        bullets: [
          "<strong>Earnings</strong> = all income credited to your wallet from the plan (combined total).",
          "<strong>Referral Income</strong> — 5% when your direct referral activates a package.",
          "<strong>Matching Income</strong> — 10% on each 1:1 pair (active Left $50 + active Right $50). Free join does not count.",
          "<strong>Rank Bonus Income</strong> — weekly unlock by achieved rank (52 weeks Locked → Unlock to wallet).",
          "<strong>Daily Yield</strong> — $0.25/day on pure $50 (max 400 days). Stops the moment you hit first rank (Starter).",
        ],
        note: "This box shows the sum of Referral + Matching + Rank Bonus + Daily Yield. Deposits and transfers are not counted here.",
        share:
          "Arowin Earnings Policy\n\nEarnings = total plan income:\n• Referral — 5% on direct activation\n• Matching — 10% on 1:1 ($50 L + $50 R)\n• Rank Bonus — weekly by rank (52 weeks)\n• Daily Yield — $0.25/day on pure $50 (max 400 days)\n\nDeposits/transfers are separate from Earnings.",
      },
      referral: {
        title: "Referral Income Policy",
        lead: "How you earn Referral Income",
        bullets: [
          "When someone you personally sponsor activates a package, you earn <strong>5% Direct Referral</strong> of their package amount.",
          "Example: your referral buys <strong>$50</strong> → you get <strong>$2.50</strong>; <strong>$150</strong> → <strong>$7.50</strong>.",
          "Paid to your <strong>Sponsor ID</strong> wallet instantly on successful activation.",
          "If the sponsor is a power ID, income goes to the <strong>power owner</strong>.",
          "Each activation pays referral income <strong>once</strong> (no double credit).",
        ],
        note: "Referral Income is separate from Matching, Rank Bonus, and Daily Yield.",
        share:
          "Arowin Referral Income Policy\n\n• 5% of package amount when your direct referral activates\n• Example: $50 → $2.50 | $150 → $7.50\n• Paid to sponsor wallet on activation\n• One credit per activation",
      },
      matching: {
        title: "Matching Income Policy",
        lead: "How you earn Matching Income",
        bullets: [
          "Binary <strong>1:1 matching</strong>: one <strong>Left $50 USDT</strong> active package pairs with one <strong>Right $50 USDT</strong> active package.",
          "<strong>Free join / pending IDs do not count</strong> — both legs must be <strong>active package holders</strong>.",
          "You (upline) must also be <strong>active with a package</strong> to receive Matching Income.",
          "On each completed pair you earn <strong>10% Matching Income</strong> of the $50 unit (base $5/pair).",
          "Higher ranks unlock higher pair rates and <strong>daily pair caps</strong>.",
          "Daily cap resets each <strong>India calendar day</strong>; leftover pairs carry to the next day.",
          "Unmatched volume stays as <strong>Carry Forward Business</strong> until it pairs.",
        ],
        note: "Rule: Active L $50 + Active R $50 → Matching Income. Free registration alone never adds matching.",
        share:
          "Arowin Matching Income Policy\n\n• 1:1 pair: Left $50 + Right $50 ACTIVE packages only\n• Free join does NOT count\n• Upline must be active with a package\n• Earn 10% Matching Income per pair\n• Higher ranks: higher rates + daily pair caps\n• Leftover volume = Carry Forward Business",
      },
      rank: {
        title: "Rank Bonus Income Policy",
        lead: "How you earn Rank Bonus Income",
        bullets: [
          "First rank: <strong>1 active $50 pack Left : 1 active $50 pack Right</strong> → <strong>Starter</strong>. Power IDs and live IDs use the same rule.",
          "After Starter, higher ranks count only IDs that already have <strong>Starter rank</strong> (not raw ID count). Example: $1550 fill → 7 starters L – 7 starters R → <strong>Silver</strong>.",
          "Starter bonus: <strong>$4 / week</strong> for <strong>52 weeks</strong>.",
          "All <strong>52 weeks start Locked</strong>. Every week one slab <strong>Unlocks → master wallet</strong> (not daily).",
          "Next rank achievement <strong>increases weekly earnings</strong> (higher slab, fresh 52-week schedule).",
          "Total potential = <strong>Weekly × 52</strong> for that rank (see table).",
        ],
        note: "Rank Bonus is weekly unlock income. Daily Yield ($0.25/day on pure $50) is a different income.",
        table: true,
        share:
          "Arowin Rank Bonus Income Policy\n\n• Starter = 1 active $50 L + 1 active $50 R (Power ID or live ID)\n• Higher ranks count Starter IDs only, not raw IDs\n• $1550 fill → 7L:7R Starters → Silver (not Gold)\n• 52 weeks Locked → unlock weekly to wallet\n\nRank | Weekly | Weeks | Total\nStarter $4 | 52 | $208\nBronze $6 | 52 | $312\nSilver $10 | 52 | $520\nGold $16 | 52 | $832\nPlatina $31 | 52 | $1,612\nDiamond $50 | 52 | $2,600\nBlue Sapphire $125 | 52 | $6,500\nRuby Elite $250 | 52 | $13,000\nEmerald Crown $500 | 52 | $26,000\nTitanium King $1,000 | 52 | $52,000\nRoyal Legend $2,500 | 52 | $130,000\nGlobal Ambassador $10,000 | 52 | $520,000",
      },
      daily: {
        title: "Daily Yield Policy",
        lead: "How you earn Daily Yield on Arowin",
        bullets: [
          "<strong>Start:</strong> Activate a <strong>pure $50</strong> package only (not $150 / $350 / split packages).",
          "Earn <strong>$0.25 per day</strong> → credited to your wallet automatically.",
          "Maximum: <strong>400 days</strong> (up to <strong>$100</strong> total).",
          "<strong>Stop (first rank):</strong> As soon as you achieve <strong>Starter rank</strong> (1 Left + 1 Right active starters), Daily Yield <strong>stops immediately</strong> — no credit on that day or after.",
          "<strong>Stop (upgrade):</strong> Also stops if you upgrade to a higher package.",
          "Power / auto IDs are <strong>not eligible</strong>.",
        ],
        note: "First rank = Starter. Rank Bonus can start when Starter unlocks; Daily Yield ends at that same moment. Separate incomes.",
        share:
          "Arowin Daily Yield Policy\n\n• Start: pure $50 package only\n• Earn $0.25/day (max 400 days / $100)\n• STOP immediately on first rank = Starter (1L + 1R active starters)\n• Also stops on package upgrade\n• Power IDs not eligible\n• Separate from Rank Bonus Income",
      },
      carry: {
        title: "Carry Forward Business Policy",
        lead: "How Carry Forward Business works",
        bullets: [
          "Your binary team builds <strong>Left</strong> and <strong>Right</strong> business volume.",
          "Matching pays on the <strong>weaker side pairs</strong> (1:1). Extra volume on the stronger side is not paid yet.",
          "That unpaid leftover is shown as <strong>Carry Forward Business</strong> (Left / Right).",
          "When new volume arrives on the weaker side, carried volume can <strong>pair again</strong> for Matching Income.",
          "Carry forward does <strong>not expire</strong> day-to-day — it waits for a matching pair.",
        ],
        note: "Carry Forward is volume waiting to match — it is not a cash balance until pairs form and Matching Income is paid.",
        share:
          "Arowin Carry Forward Business Policy\n\n• Leftover L/R volume after 1:1 matching\n• Waits to pair with new opposite-leg volume\n• Does not expire daily\n• Becomes Matching Income only when pairs form",
      },
    };

    const rankBonusSchedule = [
      { name: "Starter", weekly: 4, weeks: 52, total: 208 },
      { name: "Bronze", weekly: 6, weeks: 52, total: 312 },
      { name: "Silver", weekly: 10, weeks: 52, total: 520 },
      { name: "Gold", weekly: 16, weeks: 52, total: 832 },
      { name: "Platina", weekly: 31, weeks: 52, total: 1612 },
      { name: "Diamond", weekly: 50, weeks: 52, total: 2600 },
      { name: "Blue Sapphire", weekly: 125, weeks: 52, total: 6500 },
      { name: "Ruby Elite", weekly: 250, weeks: 52, total: 13000 },
      { name: "Emerald Crown", weekly: 500, weeks: 52, total: 26000 },
      { name: "Titanium King", weekly: 1000, weeks: 52, total: 52000 },
      { name: "Royal Legend", weekly: 2500, weeks: 52, total: 130000 },
      { name: "Global Ambassador", weekly: 10000, weeks: 52, total: 520000 },
    ];

    function fmtMoney(n) {
      return "$" + Number(n).toLocaleString("en-US", { maximumFractionDigits: 0 });
    }

    function rankPolicyTableHtml() {
      const rows = rankBonusSchedule
        .map(
          (r) =>
            "<tr>" +
            "<td><strong>" +
            r.name +
            "</strong></td>" +
            '<td class="is-num">' +
            fmtMoney(r.weekly) +
            "</td>" +
            "<td>" +
            r.weeks +
            " Weeks</td>" +
            '<td class="is-num">' +
            fmtMoney(r.total) +
            "</td>" +
            "</tr>"
        )
        .join("");
      return (
        '<div class="dy-policy-table-wrap">' +
        '<table class="dy-policy-table">' +
        "<thead><tr>" +
        "<th>Rank</th>" +
        '<th class="is-num">Weekly Income ($)</th>' +
        "<th>Duration</th>" +
        '<th class="is-num">Total Income ($)</th>' +
        "</tr></thead><tbody>" +
        rows +
        "</tbody></table></div>"
      );
    }

    function renderPolicy(key) {
      const p = policies[key] || policies.daily;
      titleEl.textContent = p.title;
      let html =
        '<p class="dy-policy-lead">' +
        p.lead +
        "</p><ul>" +
        p.bullets.map((b) => "<li>" + b + "</li>").join("") +
        "</ul>";
      if (p.table) {
        html +=
          '<p class="dy-policy-table-caption">Weekly Rank Bonus schedule</p>' +
          rankPolicyTableHtml();
      }
      html += '<p class="dy-policy-note">' + p.note + "</p>";
      bodyEl.innerHTML = html;
      shareText = p.share;
    }

    function openPolicy(key) {
      renderPolicy(key);
      overlay.classList.remove("hidden");
    }
    function closePolicy() {
      overlay.classList.add("hidden");
    }

    document.querySelectorAll(".dy-policy-link[data-policy]").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        openPolicy(btn.getAttribute("data-policy") || "daily");
      });
    });

    closeBtn?.addEventListener("click", closePolicy);
    okBtn?.addEventListener("click", closePolicy);
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) closePolicy();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && !overlay.classList.contains("hidden")) {
        closePolicy();
      }
    });

    shareBtn?.addEventListener("click", async () => {
      const text = shareText || "Arowin policy";
      try {
        if (navigator.share) {
          await navigator.share({ title: titleEl.textContent || "Arowin Policy", text });
          return;
        }
      } catch (_) {
        /* cancelled */
      }
      window.open(
        "https://wa.me/?text=" + encodeURIComponent(text),
        "_blank",
        "noopener,noreferrer"
      );
    });
  })();

  // Income box history — open sheet inside dashboard (no history page redirect)
  (function initIncomeHistory() {
    const overlay = document.getElementById("ihOverlay");
    if (!overlay) return;

    const titleEl = document.getElementById("ihTitle");
    const subEl = document.getElementById("ihSub");
    const bodyEl = document.getElementById("ihBody");
    const closeBtn = document.getElementById("ihClose");
    const api = window.AROWIN_INCOME_HISTORY_API || "";
    let abortCtrl = null;

    function esc(s) {
      return String(s ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
    }

    function closeHist() {
      overlay.classList.add("hidden");
      if (abortCtrl) {
        try {
          abortCtrl.abort();
        } catch (_) {
          /* ignore */
        }
        abortCtrl = null;
      }
    }

    function renderRows(data) {
      titleEl.textContent = data.title || "History";
      if (data.subtitle) {
        subEl.hidden = false;
        subEl.textContent = data.subtitle;
      } else if (data.total_fmt && Array.isArray(data.rows) && data.rows.length) {
        subEl.hidden = false;
        subEl.textContent = "Shown credits: " + data.total_fmt;
      } else {
        subEl.hidden = true;
        subEl.textContent = "";
      }

      const rows = Array.isArray(data.rows) ? data.rows : [];
      const stopNotice = String(data.stop_notice || "").trim();
      let head = "";
      if (stopNotice) {
        head =
          '<div class="ih-stop-notice">' +
          '<span class="ih-stop-label">Income stopped</span>' +
          '<p class="ih-stop-text">' +
          esc(stopNotice) +
          "</p>" +
          "</div>";
      }

      if (!rows.length) {
        bodyEl.innerHTML =
          head +
          '<p class="ih-empty">' +
          esc(data.message || "No history available.") +
          "</p>";
        return;
      }

      const isWeeks = data.view === "weeks" || data.kind === "rank";
      if (isWeeks && data.next_release_fmt) {
        head +=
          '<div class="ih-next-pay">' +
          '<span class="ih-next-label">Next payment release</span>' +
          '<strong class="ih-next-date">' +
          esc(data.next_release_fmt) +
          "</strong>" +
          (data.total_fmt
            ? '<span class="ih-next-total">Total credited: ' + esc(data.total_fmt) + "</span>"
            : "") +
          "</div>";
      }

      bodyEl.innerHTML =
        head +
        '<ul class="ih-list' +
        (isWeeks ? " ih-list--weeks" : "") +
        '">' +
        rows
          .map((r) => {
            const note = r.note ? '<p class="ih-row-note">' + esc(r.note) + "</p>" : "";
            const status = r.status
              ? '<span class="ih-status is-' + esc(r.status) + '">' + esc(r.note || r.status) + "</span>"
              : "";
            const amtClass =
              r.status === "locked" || r.status === "stopped" || r.status === "upcoming"
                ? " is-muted"
                : "";
            return (
              '<li class="ih-row' +
              (r.status ? " is-" + esc(r.status) : "") +
              '">' +
              '<div class="ih-row-main">' +
              '<div class="ih-row-label">' +
              esc(r.label || "Credit") +
              "</div>" +
              (isWeeks ? status : note) +
              '<p class="ih-row-when">' +
              esc(r.when || "") +
              "</p>" +
              "</div>" +
              '<div class="ih-row-amt' +
              amtClass +
              '">' +
              esc(r.amount_fmt || "") +
              "</div>" +
              "</li>"
            );
          })
          .join("") +
        "</ul>";
    }

    async function openHist(kind) {
      if (!api) {
        titleEl.textContent = "History";
        subEl.hidden = true;
        bodyEl.innerHTML = '<p class="ih-error">History API not configured.</p>';
        overlay.classList.remove("hidden");
        return;
      }

      const emptyFallback =
        kind === "rank"
          ? "No history available. Achieve a rank to start Rank Bonus."
          : "No history available.";

      titleEl.textContent = "History";
      subEl.hidden = true;
      bodyEl.innerHTML = '<p class="ih-loading">Loading…</p>';
      overlay.classList.remove("hidden");

      if (abortCtrl) {
        try {
          abortCtrl.abort();
        } catch (_) {
          /* ignore */
        }
      }
      abortCtrl = new AbortController();
      const myCtrl = abortCtrl;
      const timeoutId = setTimeout(function () {
        try {
          myCtrl.abort();
        } catch (_) {}
      }, 12000);

      try {
        const url =
          api +
          (api.includes("?") ? "&" : "?") +
          "kind=" +
          encodeURIComponent(kind) +
          "&limit=" +
          (kind === "rank" ? "60" : "25");
        const res = await fetch(url, {
          credentials: "same-origin",
          headers: { Accept: "application/json" },
          signal: myCtrl.signal,
        });
        clearTimeout(timeoutId);
        if (abortCtrl !== myCtrl) return;
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
          bodyEl.innerHTML =
            '<p class="ih-empty">' + esc(data.message || emptyFallback) + "</p>";
          return;
        }
        if (!Array.isArray(data.rows) || !data.rows.length) {
          data.message = data.message || emptyFallback;
        }
        renderRows(data);
      } catch (err) {
        clearTimeout(timeoutId);
        if (abortCtrl !== myCtrl) return;
        if (err && err.name === "AbortError") {
          bodyEl.innerHTML = '<p class="ih-empty">' + esc(emptyFallback) + "</p>";
          return;
        }
        bodyEl.innerHTML = '<p class="ih-empty">' + esc(emptyFallback) + "</p>";
      }
    }

    document.querySelectorAll(".stat-card.is-hist[data-income-hist]").forEach((card) => {
      const open = () => openHist(card.getAttribute("data-income-hist") || "earnings");
      card.addEventListener("click", open);
      card.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          open();
        }
      });
    });

    closeBtn?.addEventListener("click", closeHist);
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) closeHist();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && !overlay.classList.contains("hidden")) {
        closeHist();
      }
    });
  })();

  (function initTeamShareLinks() {
    async function copyText(text) {
      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(text);
          return true;
        }
      } catch (_) {
        /* fall through */
      }
      const ta = document.createElement("textarea");
      ta.value = text;
      ta.setAttribute("readonly", "");
      ta.style.position = "fixed";
      ta.style.left = "-9999px";
      document.body.appendChild(ta);
      ta.select();
      let ok = false;
      try {
        ok = document.execCommand("copy");
      } catch (_) {
        ok = false;
      }
      document.body.removeChild(ta);
      return ok;
    }

    document.querySelectorAll(".stat-share-btn[data-share-url]").forEach((btn) => {
      btn.addEventListener("click", async (e) => {
        e.preventDefault();
        e.stopPropagation();
        const url = String(btn.getAttribute("data-share-url") || "");
        if (!url) return;
        const ok = await copyText(url);
        const original = btn.textContent || "Copy";
        btn.textContent = ok ? btn.getAttribute("data-share-copied") || "Copied" : "Copy failed";
        btn.classList.toggle("is-copied", ok);
        setTimeout(() => {
          btn.textContent = original;
          btn.classList.remove("is-copied");
        }, 1600);
      });
    });

    document.querySelectorAll(".stat-share-icon[data-share-url]").forEach((btn) => {
      btn.addEventListener("click", async (e) => {
        e.preventDefault();
        e.stopPropagation();
        const url = String(btn.getAttribute("data-share-url") || "");
        if (!url) return;
        try {
          if (navigator.share) {
            // Put the register URL in `text` too — Android often shares the
            // current dashboard page when only `url` is set.
            await navigator.share({
              title: "Arowin Register",
              text: url,
              url,
            });
            return;
          }
        } catch (err) {
          if (err && err.name === "AbortError") return;
        }
        const ok = await copyText(url);
        const copyBtn = btn.parentElement && btn.parentElement.querySelector(".stat-share-btn");
        if (copyBtn && ok) {
          const original = copyBtn.textContent || "Copy";
          copyBtn.textContent = "Copied";
          copyBtn.classList.add("is-copied");
          setTimeout(() => {
            copyBtn.textContent = original;
            copyBtn.classList.remove("is-copied");
          }, 1600);
        }
      });
    });
  })();
});
