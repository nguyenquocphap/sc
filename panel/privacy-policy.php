<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Privacy Policy - NroOnline / Ngọc Rồng</title>
<link rel="icon" type="image/jpeg" href="img/icon.jpg">
<link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&family=Cormorant+Garamond:wght@400;600;700&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --c1: #001f3d;
  --c3: #0077b6;
  --c5: #00b4d8;
  --c6: #48cae4;
  --c7: #90e0ef;
  --c8: #caf0f8;
  --gold: #ffd60a;
  --text: #e8f4fd;
  --text2: #90cfe8;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

html { scroll-behavior: smooth; }

body {
  min-height: 100vh;
  font-family: 'Cormorant Garamond', serif;
  color: var(--text);
  background: #020e1f;
  overflow-x: hidden;
}

.bg-fixed {
  position: fixed;
  inset: 0;
  z-index: 0;
}

.bg-img {
  position: absolute;
  inset: 0;
  background: url('img/1.jpg') center/cover no-repeat;
  opacity: 0.32;
}

.bg-grad {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(180deg, rgba(2,14,31,0.62) 0%, rgba(0,21,47,0.42) 38%, rgba(2,14,31,0.86) 100%),
    radial-gradient(ellipse 70% 44% at 50% 0%, rgba(0,180,216,0.18), transparent 68%);
}

.bg-scan {
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,180,216,0.014) 2px, rgba(0,180,216,0.014) 4px);
}

.page {
  position: relative;
  z-index: 1;
  width: min(1040px, calc(100% - 2rem));
  margin: 0 auto;
  padding: clamp(1.25rem, 3vw, 2rem) 0 3rem;
}

.topbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  padding: 1rem 0 1.5rem;
}

.brand {
  color: var(--c8);
  font-family: 'Rajdhani', sans-serif;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-decoration: none;
  text-transform: uppercase;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.62rem 1rem;
  border: 1px solid rgba(144,224,239,0.36);
  border-radius: 999px;
  color: var(--c8);
  background: rgba(0,30,60,0.4);
  font-family: 'Rajdhani', sans-serif;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  text-decoration: none;
  transition: 0.25s;
}

.back-link:hover {
  color: #fff;
  border-color: rgba(255,214,10,0.68);
  box-shadow: 0 0 20px rgba(255,214,10,0.18);
  transform: translateY(-2px);
}

.hero {
  text-align: center;
  padding: clamp(2rem, 5vw, 4.5rem) 0 clamp(1.5rem, 4vw, 2.5rem);
}

.eyebrow {
  color: var(--gold);
  font-family: 'Rajdhani', sans-serif;
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: 0.2em;
  text-transform: uppercase;
}

h1 {
  margin-top: 0.7rem;
  font-family: 'Cinzel Decorative', serif;
  font-size: clamp(2.35rem, 7vw, 5.4rem);
  line-height: 1.05;
  color: #fff;
  text-shadow: 0 0 22px rgba(0,180,216,0.42), 0 0 42px rgba(0,100,180,0.25);
}

.subtitle {
  max-width: 760px;
  margin: 1rem auto 0;
  color: var(--c8);
  font-size: clamp(1.05rem, 2.6vw, 1.35rem);
  line-height: 1.7;
}

.updated {
  display: inline-flex;
  margin-top: 1.2rem;
  padding: 0.55rem 1rem;
  border: 1px solid rgba(0,180,216,0.34);
  border-radius: 999px;
  background: rgba(0,30,60,0.44);
  color: var(--c7);
  font-family: 'Rajdhani', sans-serif;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.policy-card {
  border: 1px solid rgba(0,150,199,0.28);
  border-radius: 20px;
  padding: clamp(1.15rem, 4vw, 2.35rem);
  background: linear-gradient(180deg, rgba(0,24,54,0.78), rgba(0,17,42,0.72));
  box-shadow: 0 18px 60px rgba(0,0,0,0.34), inset 0 1px 0 rgba(255,255,255,0.08);
  backdrop-filter: blur(20px);
}

.intro {
  color: var(--c8);
  font-size: 1.18rem;
  line-height: 1.75;
}

.policy-section {
  margin-top: 1.8rem;
  padding-top: 1.5rem;
  border-top: 1px solid rgba(144,224,239,0.16);
}

h2 {
  color: #fff;
  font-family: 'Rajdhani', sans-serif;
  font-size: clamp(1.35rem, 3.2vw, 1.85rem);
  letter-spacing: 0.06em;
}

h3 {
  margin-top: 1.1rem;
  color: var(--gold);
  font-family: 'Rajdhani', sans-serif;
  font-size: 1.12rem;
  letter-spacing: 0.05em;
}

p, li {
  color: var(--text);
  font-size: 1.08rem;
  line-height: 1.72;
}

p { margin-top: 0.8rem; }

ul {
  display: grid;
  gap: 0.3rem;
  margin: 0.75rem 0 0 1.25rem;
}

li::marker { color: var(--c6); }

a {
  color: var(--c8);
  text-underline-offset: 4px;
}

.contact-box {
  margin-top: 1rem;
  padding: 1rem 1.1rem;
  border: 1px solid rgba(255,214,10,0.24);
  border-radius: 14px;
  background: rgba(255,214,10,0.07);
}

footer {
  position: relative;
  z-index: 1;
  text-align: center;
  padding: 2rem 1rem 2.5rem;
  color: rgba(232,244,253,0.68);
  font-family: 'Rajdhani', sans-serif;
}

@media (max-width: 640px) {
  .topbar {
    align-items: flex-start;
    flex-direction: column;
  }

  .back-link {
    width: 100%;
    justify-content: center;
  }

  .policy-card {
    border-radius: 16px;
  }
}
</style>
</head>
<body>
<div class="bg-fixed" aria-hidden="true">
  <div class="bg-img"></div>
  <div class="bg-grad"></div>
  <div class="bg-scan"></div>
</div>

<main class="page">
  <div class="topbar">
    <a class="brand" href="index.php">NroOnline / Ngọc Rồng</a>
    <a class="back-link" href="index.php">← Back Home</a>
  </div>

  <section class="hero">
    <p class="eyebrow">Official Notice</p>
    <h1>Privacy Policy</h1>
    <p class="subtitle">This Privacy Policy explains how NroOnline / Ngọc Rồng collects, uses, stores, and protects information when you use our mobile game, website, and related services.</p>
    <p class="updated">Last updated: May 21, 2026</p>
  </section>

  <article class="policy-card">
    <p class="intro">By using NroOnline / Ngọc Rồng, you agree to the collection and use of information in accordance with this Privacy Policy.</p>

    <section class="policy-section">
      <h2>1. Information We Collect</h2>
      <p>We may collect the following types of information:</p>
      <h3>1.1 Account Information</h3>
      <p>When you create or use an account, we may collect:</p>
      <ul>
        <li>Username</li>
        <li>Password or authentication credentials</li>
        <li>Email address, if provided</li>
        <li>Account ID</li>
        <li>Login status</li>
        <li>Account creation date</li>
        <li>Account activity related to the game</li>
      </ul>
      <h3>1.2 Game Data</h3>
      <p>We collect data required to operate the game, including:</p>
      <ul>
        <li>Character name</li>
        <li>Character level</li>
        <li>In-game progress</li>
        <li>Inventory and item data</li>
        <li>Game server data</li>
        <li>In-game actions and gameplay history</li>
        <li>Ban, moderation, or security records if applicable</li>
      </ul>
      <h3>1.3 Device and Technical Information</h3>
      <p>We may collect technical information to keep the game stable and secure, including:</p>
      <ul>
        <li>Device model</li>
        <li>Operating system version</li>
        <li>App version</li>
        <li>IP address</li>
        <li>Language and region settings</li>
        <li>Network connection information</li>
        <li>Crash logs</li>
        <li>Performance data</li>
        <li>Diagnostic information</li>
      </ul>
      <h3>1.4 Support and Feedback Information</h3>
      <p>If you contact us for support or send feedback, we may collect:</p>
      <ul>
        <li>Your contact information</li>
        <li>Your support request</li>
        <li>Screenshots or files you provide</li>
        <li>Messages you send to us</li>
        <li>Information needed to resolve your issue</li>
      </ul>
      <h3>1.5 TestFlight Beta Testing Information</h3>
      <p>If you install the beta version of the app through TestFlight, Apple may provide us with beta testing information such as crash logs, usage information, and feedback you submit through TestFlight. We use this information to improve the app and fix issues before public release.</p>
    </section>

    <section class="policy-section">
      <h2>2. How We Use Information</h2>
      <p>We use the collected information to:</p>
      <ul>
        <li>Create and manage user accounts</li>
        <li>Allow users to sign in and play the game</li>
        <li>Save game progress and character data</li>
        <li>Operate game servers and online features</li>
        <li>Detect and prevent cheating, fraud, abuse, and unauthorized access</li>
        <li>Provide customer support</li>
        <li>Fix bugs and crashes</li>
        <li>Improve gameplay, performance, and user experience</li>
        <li>Communicate with users about support, updates, or important service notices</li>
        <li>Comply with legal obligations and App Store requirements</li>
      </ul>
    </section>

    <section class="policy-section">
      <h2>3. Legal Basis for Processing</h2>
      <p>Where required by applicable law, we process personal information based on one or more of the following legal bases:</p>
      <ul>
        <li>Your consent</li>
        <li>Performance of a service requested by you</li>
        <li>Our legitimate interests in operating and improving the game</li>
        <li>Compliance with legal obligations</li>
        <li>Protection of the security and integrity of our services</li>
      </ul>
    </section>

    <section class="policy-section">
      <h2>4. Sharing of Information</h2>
      <p>We do not sell your personal information.</p>
      <p>We may share limited information only in the following cases:</p>
      <ul>
        <li>With service providers that help us operate the game, website, servers, analytics, crash reporting, or customer support</li>
        <li>With Apple when required for App Store, TestFlight, crash reporting, or platform-related services</li>
        <li>When required by law, regulation, legal process, or government request</li>
        <li>To protect the rights, safety, and security of our users, our services, or the public</li>
        <li>In connection with a business transfer, merger, acquisition, or sale of assets, if applicable</li>
      </ul>
    </section>

    <section class="policy-section">
      <h2>5. Third-Party Services</h2>
      <p>Our app or services may use third-party services for hosting, analytics, crash reporting, authentication, or app distribution. These third-party services may collect information according to their own privacy policies.</p>
      <p>Examples may include:</p>
      <ul>
        <li>Apple App Store and TestFlight</li>
        <li>Server hosting providers</li>
        <li>Analytics or crash reporting tools, if used</li>
        <li>Authentication or notification services, if used</li>
      </ul>
      <p>We recommend that you review the privacy policies of any third-party services that may apply.</p>
    </section>

    <section class="policy-section">
      <h2>6. Data Retention</h2>
      <p>We retain personal information only for as long as necessary to provide the game and related services, resolve disputes, enforce our rules, prevent fraud or abuse, comply with legal obligations, and maintain security.</p>
      <p>Game account data and character data may be retained while your account remains active. Some records may be kept for a reasonable period after account deletion if required for legal, security, anti-fraud, or dispute resolution purposes.</p>
    </section>

    <section class="policy-section">
      <h2>7. Account Deletion</h2>
      <p>If the app allows users to create an account, users may request deletion of their account and associated personal data.</p>
      <p>You may request account deletion by:</p>
      <ul>
        <li>Using the account deletion feature inside the app, if available; or</li>
        <li>Contacting us at: <a href="mailto:j97inmylove@gmail.com">j97inmylove@gmail.com</a></li>
      </ul>
      <p>When we receive a valid deletion request, we will delete or anonymize your account information and associated personal data unless we are required to retain certain information for legal, security, anti-fraud, or dispute resolution purposes.</p>
      <p>Please note that deleting your account may permanently remove your game progress, characters, items, and related in-game data.</p>
    </section>

    <section class="policy-section">
      <h2>8. Security</h2>
      <p>We use reasonable technical and organizational measures to protect user information from unauthorized access, loss, misuse, alteration, or disclosure.</p>
      <p>However, no method of transmission over the Internet or electronic storage is completely secure. We cannot guarantee absolute security.</p>
    </section>

    <section class="policy-section">
      <h2>9. Children's Privacy</h2>
      <p>NroOnline / Ngọc Rồng is not intended for children under the age required by applicable law to provide consent for data processing.</p>
      <p>We do not knowingly collect personal information from children without appropriate consent. If you believe that a child has provided us with personal information without proper consent, please contact us so we can review and delete the information if required.</p>
    </section>

    <section class="policy-section">
      <h2>10. International Data Transfers</h2>
      <p>Your information may be stored and processed in countries other than your country of residence. These countries may have data protection laws different from those in your jurisdiction.</p>
      <p>By using our services, you understand that your information may be transferred to and processed in such countries where our servers, service providers, or partners operate.</p>
    </section>

    <section class="policy-section">
      <h2>11. Your Rights</h2>
      <p>Depending on your location, you may have rights regarding your personal information, including the right to:</p>
      <ul>
        <li>Access the personal information we hold about you</li>
        <li>Request correction of inaccurate information</li>
        <li>Request deletion of your personal information</li>
        <li>Object to or restrict certain processing</li>
        <li>Withdraw consent where processing is based on consent</li>
        <li>Contact a data protection authority if applicable</li>
      </ul>
      <p>To exercise these rights, please contact us at: <a href="mailto:j97inmylove@gmail.com">j97inmylove@gmail.com</a></p>
    </section>

    <section class="policy-section">
      <h2>12. Changes to This Privacy Policy</h2>
      <p>We may update this Privacy Policy from time to time. When we update it, we will change the "Last updated" date at the top of this page.</p>
      <p>Your continued use of the app after changes are posted means you accept the updated Privacy Policy.</p>
    </section>

    <section class="policy-section">
      <h2>13. Contact Us</h2>
      <p>If you have any questions about this Privacy Policy or how your information is handled, please contact us:</p>
      <div class="contact-box">
        <p>Email: <a href="mailto:j97inmylove@gmail.com">j97inmylove@gmail.com</a></p>
        <p>Website: <a href="https://srcnrofree.online/">https://srcnrofree.online/</a></p>
        <p>App name: NroOnline / Ngọc Rồng</p>
      </div>
    </section>
  </article>
</main>

<footer>© NroOnline / Ngọc Rồng</footer>
</body>
</html>
