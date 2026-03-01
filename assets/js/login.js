/**
 * bloom-aura-1/assets/js/login.js
 * ─────────────────────────────────────────────────────────────
 * JS for pages/login.php ONLY.
 * Matches bloom_aura.html reference behaviour exactly:
 *  - switchLoginTab()   tab switcher (Sign In / Create Account)
 *  - toggleLoginPass()  show/hide password on sign-in field
 *  - toggleSignupPass() show/hide password on sign-up field
 *  - signupPassHint()   live strength hint below password field
 *  - socialToast()      friendly "not connected" alert for social buttons
 *
 * No inline JS in the PHP file — all event hooks are here.
 * ─────────────────────────────────────────────────────────────
 */

/* ── Tab switcher ───────────────────────────────────── */
function switchLoginTab(tab) {
    var signin = document.getElementById('login-panel-signin');
    var signup = document.getElementById('login-panel-signup');
    var tabSignin = document.getElementById('ltab-signin');
    var tabSignup = document.getElementById('ltab-signup');

    if (tab === 'signin') {
        signin.style.display = 'block';
        signup.style.display = 'none';
        tabSignin.classList.add('active');
        tabSignup.classList.remove('active');
    } else {
        signin.style.display = 'none';
        signup.style.display = 'block';
        tabSignin.classList.remove('active');
        tabSignup.classList.add('active');
    }
}

/* ── Password visibility toggle — Sign In ─────────── */
function toggleLoginPass() {
    var inp = document.getElementById('login-pass');
    var btn = inp.parentNode.querySelector('.lfield-end');
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.textContent = '🙈';
        btn.setAttribute('aria-label', 'Hide password');
    } else {
        inp.type = 'password';
        btn.textContent = '👁';
        btn.setAttribute('aria-label', 'Show password');
    }
}

/* ── Password visibility toggle — Sign Up ────────── */
function toggleSignupPass() {
    var inp = document.getElementById('signup-pass');
    var btn = inp.parentNode.querySelector('.lfield-end');
    if (inp.type === 'password') {
        inp.type = 'text';
        btn.textContent = '🙈';
        btn.setAttribute('aria-label', 'Hide password');
    } else {
        inp.type = 'password';
        btn.textContent = '👁';
        btn.setAttribute('aria-label', 'Show password');
    }
}

/* ── Live password strength hint ────────────────── */
function signupPassHint(input) {
    var hint = document.getElementById('signup-pass-hint');
    var len  = input.value.length;

    if (len === 0) {
        hint.textContent = '8+ characters';
        hint.style.color = 'rgba(255,255,255,.3)';
    } else if (len < 8) {
        var remaining = 8 - len;
        hint.textContent = remaining + ' more character' + (remaining === 1 ? '' : 's') + ' needed';
        hint.style.color = '#fca5a5'; /* soft red — matches reference error colour */
    } else {
        hint.textContent = '✅ Looks good!';
        hint.style.color = '#86efac'; /* soft green */
    }
}

/* ── Social login placeholder ─────────────────────
   Real OAuth is not wired up — show a friendly alert.
   Replace this body with actual OAuth redirect when ready.
──────────────────────────────────────────────────── */
function socialToast(provider) {
    /* Using a native alert for now.
       In production swap for a custom toast / modal. */
    alert(provider + ' login is not connected yet.\nPlease use the email form below.');
}