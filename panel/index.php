<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#eaf7ff">
  <meta name="description" content="Ngọc Rồng Online - thế giới nhập vai, chiến đấu và phiêu lưu dành cho cộng đồng yêu thích Dragon Ball.">
  <title>Ngọc Rồng Online </title>
  <link rel="icon" type="image/jpeg" href="img/icon.jpg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&family=Chakra+Petch:wght@500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #070708;
      --surface: #101012;
      --surface-2: #171719;
      --line: rgba(255,255,255,.1);
      --text: #f6f3ed;
      --muted: #aaa6a0;
      --orange: #ff6b19;
      --yellow: #ffc928;
      --green: #55dc91;
      --font-body: "Be Vietnam Pro", Arial, sans-serif;
      --font-display: "Chakra Petch", "Arial Narrow", sans-serif;
      --container: min(1180px, calc(100% - 40px));
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      min-width: 320px;
      overflow-x: hidden;
      background: var(--bg);
      color: var(--text);
      font-family: var(--font-body);
      line-height: 1.65;
    }
    body.menu-open { overflow: hidden; }
    a { color: inherit; text-decoration: none; }
    button { color: inherit; font: inherit; }
    img { display: block; max-width: 100%; }
    ::selection { color: #111; background: var(--yellow); }

    .container { width: var(--container); margin-inline: auto; }
    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      color: var(--yellow);
      font-family: var(--font-display);
      font-size: .78rem;
      font-weight: 700;
      letter-spacing: .2em;
      text-transform: uppercase;
    }
    .eyebrow::before {
      width: 28px;
      height: 2px;
      background: var(--orange);
      content: "";
    }

    .site-header {
      position: fixed;
      z-index: 100;
      top: 0;
      left: 0;
      width: 100%;
      border-bottom: 1px solid transparent;
      transition: background .25s, border-color .25s, backdrop-filter .25s;
    }
    .site-header.scrolled {
      border-color: var(--line);
      background: rgba(7,7,8,.82);
      backdrop-filter: blur(18px);
    }
    .nav {
      display: flex;
      height: 82px;
      align-items: center;
      justify-content: space-between;
    }
    .brand {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      font-family: var(--font-display);
      font-size: 1rem;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
    }
    .brand-mark {
      display: grid;
      width: 40px;
      height: 40px;
      place-items: center;
      transform: rotate(8deg);
      border-radius: 12px 4px 12px 4px;
      background: linear-gradient(135deg, var(--yellow), var(--orange));
      color: #111;
      font-family: var(--font-display);
      font-size: 1.15rem;
      font-weight: 800;
      box-shadow: 0 0 24px rgba(255,107,25,.25);
    }
    .brand-mark img {
      width: 34px;
      height: 34px;
      object-fit: contain;
      image-rendering: pixelated;
      transform: rotate(-8deg);
    }
    .nav-links { display: flex; align-items: center; gap: 34px; }
    .nav-links a {
      position: relative;
      color: #d1cec9;
      font-size: .82rem;
      font-weight: 600;
      transition: color .2s;
    }
    .nav-links a:not(.nav-cta)::after {
      position: absolute;
      right: 0;
      bottom: -8px;
      left: 0;
      height: 2px;
      transform: scaleX(0);
      background: var(--orange);
      content: "";
      transition: transform .2s;
    }
    .nav-links a:hover { color: #fff; }
    .nav-links a:hover::after { transform: scaleX(1); }
    .nav-cta {
      padding: 11px 20px;
      border: 1px solid rgba(255,201,40,.5);
      border-radius: 8px;
      color: var(--yellow) !important;
    }
    .menu-button {
      display: none;
      width: 44px;
      height: 44px;
      border: 1px solid var(--line);
      border-radius: 10px;
      background: rgba(255,255,255,.04);
      cursor: pointer;
    }
    .menu-button span {
      display: block;
      width: 20px;
      height: 2px;
      margin: 5px auto;
      background: #fff;
      transition: .2s;
    }

    .hero {
      position: relative;
      display: flex;
      min-height: 100svh;
      align-items: center;
      overflow: hidden;
      border-bottom: 1px solid var(--line);
    }
    .hero-bg, .hero-shade, .hero-grid, .hero-glow {
      position: absolute;
      inset: 0;
      pointer-events: none;
    }
    .hero-bg {
      background: url("img/111.jpg") 67% center / cover no-repeat;
      filter: saturate(.85) contrast(1.08);
      transform: scale(1.02);
    }
    .hero-shade {
      background:
        linear-gradient(90deg, #070708 4%, rgba(7,7,8,.96) 24%, rgba(7,7,8,.68) 51%, rgba(7,7,8,.25) 76%, rgba(7,7,8,.7) 100%),
        linear-gradient(0deg, #070708 0%, transparent 33%, rgba(7,7,8,.25) 100%);
    }
    .hero-grid {
      opacity: .14;
      background-image:
        linear-gradient(rgba(255,255,255,.15) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.15) 1px, transparent 1px);
      background-size: 68px 68px;
      mask-image: linear-gradient(90deg, #000, transparent 70%);
    }
    .hero-glow {
      inset: auto auto -220px -150px;
      width: 650px;
      height: 650px;
      border-radius: 50%;
      background: rgba(255,107,25,.13);
      filter: blur(90px);
    }
    .hero-content {
      position: relative;
      z-index: 2;
      width: min(720px, 100%);
      padding: 140px 0 90px;
    }
    .hero-kicker {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 26px;
      color: #d8d4cd;
      font-family: var(--font-display);
      font-size: .78rem;
      font-weight: 600;
      letter-spacing: .18em;
      text-transform: uppercase;
    }
    .live-dot {
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: var(--green);
      box-shadow: 0 0 0 5px rgba(85,220,145,.12), 0 0 16px rgba(85,220,145,.8);
      animation: pulse 1.8s infinite;
    }
    @keyframes pulse { 50% { opacity: .45; } }
    .hero h1 {
      max-width: 750px;
      font-family: var(--font-display);
      font-size: clamp(3.3rem, 7.2vw, 6.6rem);
      font-weight: 700;
      letter-spacing: -.055em;
      line-height: .92;
      text-transform: uppercase;
      text-shadow: 0 12px 45px rgba(0,0,0,.5);
    }
    .hero h1 span {
      display: block;
      color: transparent;
      background: linear-gradient(90deg, var(--yellow), var(--orange));
      background-clip: text;
      -webkit-background-clip: text;
    }
    .hero-copy {
      max-width: 590px;
      margin: 27px 0 36px;
      color: #c0bdb7;
      font-size: clamp(.95rem, 1.4vw, 1.08rem);
    }
    .hero-actions { display: flex; flex-wrap: wrap; gap: 13px; }
    .button {
      display: inline-flex;
      min-height: 54px;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 0 25px;
      border: 1px solid transparent;
      border-radius: 9px;
      font-family: var(--font-display);
      font-size: .88rem;
      font-weight: 700;
      letter-spacing: .06em;
      text-transform: uppercase;
      transition: transform .2s, box-shadow .2s, background .2s;
    }
    .button:hover { transform: translateY(-2px); }
    .button-primary {
      background: linear-gradient(135deg, var(--yellow), var(--orange));
      color: #101010;
      box-shadow: 0 12px 35px rgba(255,107,25,.25);
    }
    .button-primary:hover { box-shadow: 0 16px 42px rgba(255,107,25,.4); }
    .button-secondary {
      border-color: rgba(255,255,255,.22);
      background: rgba(10,10,10,.4);
      backdrop-filter: blur(10px);
    }
    .button-secondary:hover { background: rgba(255,255,255,.1); }
    .button-arrow { font-size: 1.15rem; }
    .hero-stats {
      display: flex;
      gap: 0;
      margin-top: 52px;
    }
    .stat {
      min-width: 135px;
      padding-right: 28px;
      border-right: 1px solid var(--line);
      margin-right: 28px;
    }
    .stat:last-child { border: 0; margin: 0; padding: 0; }
    .stat strong {
      display: block;
      font-family: var(--font-display);
      font-size: 1.35rem;
      line-height: 1.2;
    }
    .stat span { color: var(--muted); font-size: .75rem; }
    .scroll-label {
      position: absolute;
      z-index: 3;
      right: 30px;
      bottom: 42px;
      display: flex;
      align-items: center;
      gap: 13px;
      transform: rotate(90deg);
      transform-origin: right bottom;
      color: #a7a39d;
      font-family: var(--font-display);
      font-size: .68rem;
      letter-spacing: .18em;
      text-transform: uppercase;
    }
    .scroll-label::after { width: 52px; height: 1px; background: var(--orange); content: ""; }

    .section { padding: 105px 0; }
    .section-heading {
      display: flex;
      align-items: end;
      justify-content: space-between;
      gap: 30px;
      margin-bottom: 48px;
    }
    .section-heading h2 {
      max-width: 670px;
      margin-top: 13px;
      font-family: var(--font-display);
      font-size: clamp(2rem, 4.5vw, 3.7rem);
      letter-spacing: -.04em;
      line-height: 1.05;
      text-transform: uppercase;
    }
    .section-heading p { max-width: 430px; color: var(--muted); font-size: .92rem; }

    .servers { background: #0b0b0c; }
    .server-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
    .server-card {
      position: relative;
      display: grid;
      min-height: 285px;
      grid-template-columns: 175px 1fr;
      align-items: center;
      overflow: hidden;
      padding: 35px;
      border: 1px solid var(--line);
      border-radius: 18px;
      background:
        linear-gradient(135deg, rgba(255,255,255,.05), transparent 60%),
        var(--surface);
      cursor: pointer;
      transition: transform .25s, border-color .25s, box-shadow .25s;
    }
    .server-card::after {
      position: absolute;
      right: -45px;
      bottom: -75px;
      width: 200px;
      height: 200px;
      border-radius: 50%;
      background: rgba(255,107,25,.1);
      filter: blur(25px);
      content: "";
    }
    .server-card:not(.locked):hover {
      transform: translateY(-6px);
      border-color: rgba(255,201,40,.35);
      box-shadow: 0 25px 65px rgba(0,0,0,.35);
    }
    .server-card.locked { cursor: default; opacity: .58; }
    .server-image {
      position: relative;
      z-index: 1;
      width: 145px;
      height: 145px;
      object-fit: contain;
      image-rendering: pixelated;
      filter: drop-shadow(0 20px 25px rgba(0,0,0,.45));
    }
    .server-info { position: relative; z-index: 2; }
    .status {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 18px;
      color: var(--green);
      font-size: .7rem;
      font-weight: 700;
      letter-spacing: .12em;
      text-transform: uppercase;
    }
    .status::before { width: 7px; height: 7px; border-radius: 50%; background: currentColor; content: ""; }
    .locked .status { color: #9d9992; }
    .server-info h3 {
      font-family: var(--font-display);
      font-size: clamp(1.45rem, 2.4vw, 2rem);
      line-height: 1.1;
      text-transform: uppercase;
    }
    .server-info h3 span { display: block; margin-top: 7px; color: var(--orange); font-size: .76rem; letter-spacing: .1em; }
    .server-info p { margin: 14px 0 20px; color: var(--muted); font-size: .84rem; }
    .server-enter {
      display: inline-flex;
      align-items: center;
      gap: 9px;
      color: var(--yellow);
      font-family: var(--font-display);
      font-size: .76rem;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
    }

    .feature-layout {
      display: grid;
      grid-template-columns: .88fr 1.12fr;
      gap: 70px;
      align-items: center;
    }
    .feature-visual {
      position: relative;
      min-height: 590px;
      overflow: hidden;
      border: 1px solid var(--line);
      border-radius: 24px;
      background: url("img/1.jpg") center / cover no-repeat;
    }
    .feature-visual::after {
      position: absolute;
      inset: 0;
      background: linear-gradient(0deg, rgba(7,7,8,.85), transparent 65%);
      content: "";
    }
    .visual-card {
      position: absolute;
      z-index: 2;
      right: 24px;
      bottom: 24px;
      left: 24px;
      padding: 25px;
      border: 1px solid rgba(255,255,255,.15);
      border-radius: 15px;
      background: rgba(10,10,11,.76);
      backdrop-filter: blur(15px);
    }
    .visual-card strong {
      display: block;
      margin-bottom: 6px;
      font-family: var(--font-display);
      font-size: 1.25rem;
      text-transform: uppercase;
    }
    .visual-card p { color: #bbb7b1; font-size: .82rem; }
    .feature-copy h2 {
      margin: 15px 0 22px;
      font-family: var(--font-display);
      font-size: clamp(2.2rem, 4.5vw, 4rem);
      letter-spacing: -.045em;
      line-height: 1.02;
      text-transform: uppercase;
    }
    .feature-copy > p { color: var(--muted); }
    .feature-list { display: grid; gap: 0; margin-top: 34px; }
    .feature-item {
      display: grid;
      grid-template-columns: 52px 1fr;
      gap: 18px;
      padding: 23px 0;
      border-top: 1px solid var(--line);
    }
    .feature-number {
      color: var(--orange);
      font-family: var(--font-display);
      font-size: .74rem;
      font-weight: 700;
    }
    .feature-item h3 { margin-bottom: 6px; font-family: var(--font-display); font-size: 1.05rem; text-transform: uppercase; }
    .feature-item p { color: var(--muted); font-size: .84rem; }

    .community {
      position: relative;
      overflow: hidden;
      border-block: 1px solid var(--line);
      background: #0c0c0e;
    }
    .community::before {
      position: absolute;
      top: -170px;
      left: 50%;
      width: 550px;
      height: 420px;
      transform: translateX(-50%);
      border-radius: 50%;
      background: rgba(255,107,25,.11);
      filter: blur(100px);
      content: "";
    }
    .community-head { position: relative; max-width: 790px; margin: 0 auto 55px; text-align: center; }
    .community-head .eyebrow::before { display: none; }
    .community-head h2 {
      margin: 15px 0 20px;
      font-family: var(--font-display);
      font-size: clamp(2.2rem, 5vw, 4.4rem);
      letter-spacing: -.05em;
      line-height: 1;
      text-transform: uppercase;
    }
    .community-head p { color: var(--muted); }
    .values { position: relative; display: grid; grid-template-columns: repeat(3, 1fr); border: 1px solid var(--line); border-radius: 18px; }
    .value { min-height: 240px; padding: 35px; border-right: 1px solid var(--line); }
    .value:last-child { border: 0; }
    .value-icon {
      display: grid;
      width: 44px;
      height: 44px;
      margin-bottom: 38px;
      place-items: center;
      border: 1px solid rgba(255,201,40,.3);
      border-radius: 12px;
      color: var(--yellow);
      font-family: var(--font-display);
      font-weight: 700;
    }
    .value h3 { margin-bottom: 10px; font-family: var(--font-display); font-size: 1.15rem; text-transform: uppercase; }
    .value p { color: var(--muted); font-size: .84rem; }

    .cta {
      position: relative;
      padding: 100px 0;
      overflow: hidden;
      text-align: center;
    }
    .cta::before {
      position: absolute;
      inset: 0;
      opacity: .14;
      background: url("img/111.jpg") center 42% / cover no-repeat;
      content: "";
      filter: grayscale(1);
    }
    .cta::after { position: absolute; inset: 0; background: linear-gradient(90deg, #070708, transparent, #070708); content: ""; }
    .cta-content { position: relative; z-index: 2; }
    .cta h2 {
      max-width: 850px;
      margin: 14px auto 25px;
      font-family: var(--font-display);
      font-size: clamp(2.4rem, 6vw, 5rem);
      letter-spacing: -.055em;
      line-height: .98;
      text-transform: uppercase;
    }
    .cta p { max-width: 600px; margin: 0 auto 32px; color: var(--muted); }

    footer { padding: 35px 0; border-top: 1px solid var(--line); color: #817e79; font-size: .74rem; }
    .footer-inner { display: flex; align-items: center; justify-content: space-between; gap: 25px; }
    .footer-links { display: flex; gap: 25px; }
    .footer-links a:hover { color: #fff; }

    .modal {
      position: fixed;
      z-index: 200;
      inset: 0;
      display: grid;
      padding: 20px;
      place-items: center;
      visibility: hidden;
      opacity: 0;
      background: rgba(0,0,0,.78);
      backdrop-filter: blur(12px);
      transition: opacity .2s, visibility .2s;
    }
    .modal.open { visibility: visible; opacity: 1; }
    .modal-card {
      width: min(460px, 100%);
      padding: 34px;
      transform: translateY(15px);
      border: 1px solid rgba(255,255,255,.14);
      border-radius: 20px;
      background: #121214;
      box-shadow: 0 30px 100px rgba(0,0,0,.6);
      transition: transform .25s;
    }
    .modal.open .modal-card { transform: translateY(0); }
    .modal-top { display: flex; align-items: center; gap: 20px; }
    .modal-icon { width: 78px; height: 78px; object-fit: contain; image-rendering: pixelated; }
    .modal-top h3 { font-family: var(--font-display); font-size: 1.65rem; text-transform: uppercase; }
    .modal-top p { color: var(--green); font-size: .76rem; font-weight: 700; }
    .modal-copy { margin: 25px 0; color: var(--muted); font-size: .87rem; }
    .modal-actions { display: grid; grid-template-columns: 1fr 1.5fr; gap: 10px; }
    .modal-actions button, .modal-actions a {
      min-height: 49px;
      border: 1px solid var(--line);
      border-radius: 8px;
      background: transparent;
      cursor: pointer;
    }
    .modal-actions a { display: grid; place-items: center; border: 0; background: linear-gradient(135deg, var(--yellow), var(--orange)); color: #111; font-weight: 800; }

    [data-reveal] { transform: translateY(28px); opacity: 0; transition: transform .65s ease, opacity .65s ease; }
    [data-reveal].visible { transform: none; opacity: 1; }

    @media (max-width: 900px) {
      .nav-links {
        position: fixed;
        inset: 82px 0 auto;
        display: grid;
        padding: 28px 20px 35px;
        transform: translateY(-130%);
        border-bottom: 1px solid var(--line);
        background: rgba(7,7,8,.98);
        text-align: center;
        transition: transform .25s;
      }
      .menu-open .nav-links { transform: translateY(0); }
      .menu-button { display: block; }
      .server-grid, .feature-layout { grid-template-columns: 1fr; }
      .feature-layout { gap: 45px; }
      .feature-visual { min-height: 480px; }
      .values { grid-template-columns: 1fr; }
      .value { min-height: auto; border-right: 0; border-bottom: 1px solid var(--line); }
      .value-icon { margin-bottom: 22px; }
      .section-heading { display: block; }
      .section-heading p { margin-top: 20px; }
    }
    @media (max-width: 620px) {
      :root { --container: min(100% - 28px, 1180px); }
      .nav { height: 70px; }
      .nav-links { top: 70px; }
      .brand { font-size: .86rem; }
      .hero { min-height: 820px; }
      .hero-bg { background-position: 61% center; opacity: .67; }
      .hero-shade { background: linear-gradient(90deg, rgba(7,7,8,.98), rgba(7,7,8,.6)), linear-gradient(0deg, #070708 0%, transparent 55%); }
      .hero-content { padding: 125px 0 70px; }
      .hero h1 { font-size: clamp(3rem, 16vw, 4.4rem); }
      .hero-copy { font-size: .9rem; }
      .hero-actions { display: grid; }
      .hero-stats { flex-wrap: wrap; gap: 20px 0; margin-top: 38px; }
      .stat { min-width: 50%; margin: 0; padding: 0; border: 0; }
      .scroll-label { display: none; }
      .section { padding: 75px 0; }
      .server-grid { gap: 14px; }
      .server-card { min-height: 0; grid-template-columns: 100px 1fr; padding: 24px 18px; }
      .server-image { width: 88px; height: 88px; }
      .feature-visual { min-height: 400px; }
      .visual-card { right: 14px; bottom: 14px; left: 14px; padding: 20px; }
      .value { padding: 27px; }
      .footer-inner { flex-direction: column; align-items: flex-start; }
      .modal-card { padding: 25px; }
      .modal-actions { grid-template-columns: 1fr; }
    }
    @media (prefers-reduced-motion: reduce) {
      html { scroll-behavior: auto; }
      *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
      [data-reveal] { transform: none; opacity: 1; }
    }

    /* Light sky theme */
    :root {
      --bg: #f5fbff;
      --surface: #ffffff;
      --surface-2: #eaf6ff;
      --line: rgba(25, 83, 125, .14);
      --text: #12263a;
      --muted: #5d7285;
      --orange: #ff7426;
      --yellow: #ffb817;
      --green: #19a765;
    }
    body {
      background:
        radial-gradient(circle at 10% 20%, rgba(108, 201, 255, .16), transparent 28%),
        #f5fbff;
    }
    main {
      display: flex;
      flex-direction: column;
    }
    .servers { order: 1; }
    .hero { order: 2; }
    #tinh-nang { order: 3; }
    .community { order: 4; }
    .cta { order: 5; }
    .site-header {
      border-color: rgba(25, 83, 125, .12);
      background: rgba(255, 255, 255, .82);
      backdrop-filter: blur(18px);
    }
    .site-header.scrolled {
      border-color: rgba(25, 83, 125, .15);
      background: rgba(255, 255, 255, .92);
    }
    .brand-mark {
      color: #fff;
      box-shadow: 0 10px 28px rgba(255, 116, 38, .24);
    }
    .nav-links a { color: #405b70; }
    .nav-links a:hover { color: #0e78bb; }
    .nav-cta {
      border-color: rgba(255, 116, 38, .28);
      background: #fff4e8;
      color: #df5b13 !important;
    }
    .menu-button { background: rgba(255, 255, 255, .75); }
    .menu-button span { background: #17344b; }

    .servers {
      position: relative;
      min-height: 100svh;
      padding-top: 145px;
      overflow: hidden;
      background:
        linear-gradient(180deg, rgba(238, 249, 255, .76), rgba(245, 251, 255, .96)),
        url("img/1.jpg") center / cover no-repeat;
    }
    .servers::before {
      position: absolute;
      top: -180px;
      right: -100px;
      width: 520px;
      height: 520px;
      border-radius: 50%;
      background: rgba(64, 181, 255, .17);
      filter: blur(45px);
      content: "";
    }
    .servers .container { position: relative; z-index: 1; }
    .servers .section-heading h2 { color: #102c43; }
    .servers .section-heading p { color: #527087; }
    .eyebrow { color: #e35d15; }
    .eyebrow::before { background: #ff8d36; }
    .server-card {
      border-color: rgba(41, 119, 170, .14);
      background:
        linear-gradient(135deg, rgba(255,255,255,.98), rgba(237,248,255,.94));
      box-shadow: 0 18px 50px rgba(34, 104, 151, .12);
    }
    .server-card::after { background: rgba(255, 174, 51, .18); }
    .server-card:not(.locked):hover {
      border-color: rgba(255, 116, 38, .4);
      box-shadow: 0 25px 65px rgba(34, 104, 151, .2);
    }
    .server-info h3 { color: #132f45; }
    .server-info p { color: #647b8c; }
    .server-enter { color: #e45d15; }
    .server-card.locked {
      opacity: .72;
      background: rgba(242, 247, 250, .9);
    }

    .hero {
      min-height: 820px;
      border-color: var(--line);
      background: #dff4ff;
    }
    .hero-bg {
      background-position: 68% center;
      filter: saturate(.9) contrast(.96);
    }
    .hero-shade {
      background:
        linear-gradient(90deg, rgba(245,251,255,.98) 2%, rgba(245,251,255,.94) 28%, rgba(245,251,255,.58) 54%, rgba(245,251,255,.08) 78%),
        linear-gradient(0deg, #f5fbff 0%, transparent 30%);
    }
    .hero-grid {
      opacity: .2;
      background-image:
        linear-gradient(rgba(34, 112, 164, .16) 1px, transparent 1px),
        linear-gradient(90deg, rgba(34, 112, 164, .16) 1px, transparent 1px);
    }
    .hero-glow { background: rgba(255, 148, 54, .18); }
    .hero-kicker { color: #3e637d; }
    .hero h1 {
      color: #102b42;
      text-shadow: 0 12px 40px rgba(255,255,255,.7);
    }
    .hero-copy { color: #4e6c82; }
    .button-secondary {
      border-color: rgba(28, 91, 134, .2);
      background: rgba(255,255,255,.72);
      color: #173b55;
      box-shadow: 0 10px 30px rgba(36, 101, 145, .08);
    }
    .button-secondary:hover { background: #fff; }
    .stat { border-color: rgba(25, 83, 125, .16); }
    .stat span, .scroll-label { color: #617a8d; }

    #tinh-nang { background: #fff; }
    .feature-visual {
      border-color: rgba(25, 83, 125, .14);
      box-shadow: 0 25px 60px rgba(38, 105, 150, .13);
    }
    .visual-card {
      border-color: rgba(255,255,255,.55);
      background: rgba(255,255,255,.86);
      color: #17354b;
    }
    .visual-card p { color: #5a7283; }
    .feature-item { border-color: var(--line); }

    .community {
      border-color: var(--line);
      background: #eaf7ff;
    }
    .community::before { background: rgba(62, 181, 255, .18); }
    .values {
      overflow: hidden;
      border-color: rgba(25, 83, 125, .14);
      background: rgba(255,255,255,.72);
      box-shadow: 0 20px 55px rgba(38, 105, 150, .09);
    }
    .value { border-color: var(--line); }
    .value-icon {
      border-color: rgba(255, 116, 38, .25);
      background: #fff4e8;
      color: #e45d15;
    }

    .cta { background: #fff; }
    .cta::before { opacity: .1; filter: none; }
    .cta::after {
      background: linear-gradient(90deg, #fff, rgba(255,255,255,.72), #fff);
    }
    footer {
      border-color: var(--line);
      background: #eaf7ff;
      color: #647b8c;
    }
    .modal { background: rgba(28, 65, 91, .45); }
    .modal-card {
      border-color: rgba(25, 83, 125, .15);
      background: #fff;
      box-shadow: 0 30px 100px rgba(24, 69, 99, .28);
    }
    .modal-actions button {
      border-color: rgba(25, 83, 125, .18);
      background: #f3f9fc;
      color: #27465c;
    }
    @media (max-width: 900px) {
      .nav-links {
        border-color: var(--line);
        background: rgba(255,255,255,.98);
      }
    }
    @media (max-width: 620px) {
      .servers { min-height: auto; padding-top: 115px; }
      .hero { min-height: 760px; }
      .hero-bg { opacity: .58; }
      .hero-shade {
        background:
          linear-gradient(90deg, rgba(245,251,255,.97), rgba(245,251,255,.55)),
          linear-gradient(0deg, #f5fbff 0%, transparent 55%);
      }
    }

    /* Sharper contrast */
    .site-header {
      background: rgba(255, 255, 255, .96);
      backdrop-filter: blur(8px);
    }
    .nav-links a { color: #24465f; }
    .servers {
      background:
        linear-gradient(180deg, rgba(232, 247, 255, .48), rgba(245, 251, 255, .82)),
        url("img/1.jpg") center / cover no-repeat;
    }
    .servers::before { opacity: .45; }
    .servers .section-heading {
      padding: 28px 32px;
      border: 1px solid rgba(23, 90, 135, .16);
      border-radius: 18px;
      background: rgba(255, 255, 255, .9);
      box-shadow: 0 16px 45px rgba(34, 104, 151, .12);
    }
    .servers .section-heading h2,
    .server-info h3 { color: #09253a; }
    .servers .section-heading p,
    .server-info p { color: #405e73; }
    .server-card {
      border: 2px solid rgba(29, 105, 156, .2);
      background: #fff;
      box-shadow: 0 20px 50px rgba(25, 83, 125, .18);
    }
    .server-card.locked {
      opacity: .88;
      background: #eef5f9;
    }
    .server-image { filter: drop-shadow(0 12px 12px rgba(19, 61, 89, .2)); }
    .hero-shade {
      background:
        linear-gradient(90deg, #f5fbff 0%, rgba(245,251,255,.92) 30%, rgba(245,251,255,.34) 58%, rgba(245,251,255,.02) 82%),
        linear-gradient(0deg, #f5fbff 0%, transparent 24%);
    }
    .hero h1 { color: #082940; }
    .hero-copy { color: #34566e; font-weight: 500; }
    .feature-copy > p,
    .feature-item p,
    .community-head p,
    .value p,
    .cta p { color: #49677c; }
    .values { background: #fff; }
    .visual-card {
      background: rgba(255,255,255,.96);
      backdrop-filter: none;
    }
    .modal { backdrop-filter: blur(5px); }
    @media (max-width: 620px) {
      .servers .section-heading { padding: 22px 20px; }
      .hero-bg { opacity: .78; }
      .hero-shade {
        background:
          linear-gradient(90deg, rgba(245,251,255,.98), rgba(245,251,255,.42)),
          linear-gradient(0deg, #f5fbff 0%, transparent 48%);
      }
    }

    /* Maximum clarity */
    body {
      background: #f4faff;
      color: #071f31;
    }
    .site-header {
      background: #fff;
      backdrop-filter: none;
      box-shadow: 0 2px 14px rgba(12, 59, 91, .1);
    }
    .site-header.scrolled { background: #fff; backdrop-filter: none; }
    .servers {
      background:
        linear-gradient(180deg, rgba(229, 246, 255, .2), rgba(244, 250, 255, .66)),
        url("img/1.jpg") center / cover no-repeat;
    }
    .servers::before { display: none; }
    .servers .section-heading {
      border: 2px solid #c5dfef;
      background: #fff;
      box-shadow: 0 14px 32px rgba(20, 75, 111, .16);
    }
    .servers .section-heading h2 { color: #031d30; }
    .servers .section-heading p { color: #284e68; font-weight: 500; }
    .server-card {
      border-color: #b5d5e8;
      box-shadow: 0 16px 35px rgba(15, 67, 102, .18);
    }
    .server-card:not(.locked):hover { border-color: #f06a20; }
    .server-info h3 { color: #031d30; }
    .server-info p { color: #294f68; font-weight: 500; }
    .status { font-weight: 800; }
    .server-card.locked { opacity: 1; background: #e9f1f6; }
    .server-card.locked .server-image { opacity: .65; }
    .hero-bg { filter: saturate(1.05) contrast(1.06); }
    .hero-shade {
      background:
        linear-gradient(90deg, #f5fbff 0%, rgba(245,251,255,.94) 27%, rgba(245,251,255,.18) 55%, transparent 78%),
        linear-gradient(0deg, #f5fbff 0%, transparent 18%);
    }
    .hero h1 { color: #031f33; text-shadow: none; }
    .hero-copy { color: #244a64; }
    .section-heading h2,
    .feature-copy h2,
    .community-head h2,
    .cta h2 { color: #061f32; }
    .feature-copy > p,
    .feature-item p,
    .community-head p,
    .value p,
    .cta p { color: #365b73; font-weight: 500; }
    .community { background: #e4f4fd; }
    .values { border: 2px solid #c0ddeb; }
    .value { border-color: #c0ddeb; }
    .modal { backdrop-filter: none; background: rgba(15, 47, 70, .65); }
    @media (max-width: 620px) {
      .hero-bg { opacity: .9; }
      .hero-shade {
        background:
          linear-gradient(90deg, rgba(245,251,255,.98), rgba(245,251,255,.28)),
          linear-gradient(0deg, #f5fbff 0%, transparent 42%);
      }
    }

    /* Stronger visual weight */
    body { font-weight: 500; }
    .brand, .nav-links a { font-weight: 800; }
    .nav-links a { color: #123a55; }
    .servers {
      background:
        linear-gradient(180deg, rgba(229, 246, 255, .08), rgba(244, 250, 255, .42)),
        url("img/1.jpg") center / cover no-repeat;
    }
    .servers .section-heading {
      border-color: #86bddc;
      box-shadow: 0 18px 42px rgba(8, 53, 84, .24);
    }
    .servers .section-heading h2,
    .section-heading h2,
    .feature-copy h2,
    .community-head h2,
    .cta h2,
    .hero h1 { font-weight: 700; }
    .servers .section-heading p,
    .hero-copy,
    .feature-copy > p,
    .feature-item p,
    .community-head p,
    .value p,
    .cta p { color: #183f59; font-weight: 600; }
    .server-card {
      border: 3px solid #8fc2df;
      box-shadow: 0 20px 42px rgba(7, 53, 84, .26);
    }
    .server-card:not(.locked):hover {
      border-color: #e95809;
      box-shadow: 0 26px 60px rgba(7, 53, 84, .32);
    }
    .server-info h3 { font-weight: 700; }
    .server-info p { color: #163d57; font-weight: 700; }
    .server-enter, .eyebrow, .button { font-weight: 800; }
    .server-image {
      filter: saturate(1.15) contrast(1.12) drop-shadow(0 14px 13px rgba(7, 42, 66, .3));
    }
    .hero-bg { filter: saturate(1.2) contrast(1.14); }
    .hero-shade {
      background:
        linear-gradient(90deg, #f5fbff 0%, rgba(245,251,255,.88) 25%, rgba(245,251,255,.08) 52%, transparent 73%),
        linear-gradient(0deg, #f5fbff 0%, transparent 14%);
    }
    .values {
      border: 3px solid #8fc2df;
      box-shadow: 0 24px 58px rgba(7, 53, 84, .17);
    }
    .value { border-color: #9cc8df; }
    .feature-visual {
      border: 3px solid #8fc2df;
      box-shadow: 0 28px 65px rgba(7, 53, 84, .23);
    }
    @media (max-width: 620px) {
      .hero-bg { opacity: 1; }
      .hero-shade {
        background:
          linear-gradient(90deg, rgba(245,251,255,.96), rgba(245,251,255,.16)),
          linear-gradient(0deg, #f5fbff 0%, transparent 38%);
      }
    }

    /* Universe 2 disabled state */
    .server-card.locked {
      border-color: #aeb7be;
      background: linear-gradient(135deg, #eef0f2, #dfe3e6);
      box-shadow: 0 16px 34px rgba(65, 74, 82, .2);
    }
    .server-card.locked::after { background: rgba(98, 106, 112, .12); }
    .server-card.locked .server-image {
      opacity: .62;
      filter: grayscale(1) contrast(.9) drop-shadow(0 10px 10px rgba(52, 57, 62, .2));
    }
    .server-card.locked .status,
    .server-card.locked .server-info h3,
    .server-card.locked .server-info h3 span,
    .server-card.locked .server-info p,
    .server-card.locked .server-enter {
      color: #697177;
    }
    .server-card.locked .status::before { background: #858d93; }

    .server-card:not(.locked) .server-info h3 span {
      margin-top: 7px;
      font-size: 1em;
      letter-spacing: 0;
      line-height: 1.1;
    }
    .server-highlight {
      color: #ff681f;
      font-weight: 800;
    }
  </style>
</head>
<body>
  <header class="site-header" id="header">
    <div class="container nav">
      <a class="brand" href="#top" aria-label="Ngọc Rồng Online">
        <span class="brand-mark"><img src="img/118.png" alt=""></span>
        <span>Ngọc Rồng Online</span>
      </a>
      <nav class="nav-links" aria-label="Điều hướng chính">
        <a href="#gioi-thieu">Giới thiệu</a>
        <a href="#tinh-nang">Tính năng</a>
        <a href="#cong-dong">Cộng đồng</a>
        <a class="nav-cta" href="#vu-tru">Chơi ngay</a>
      </nav>
      <button class="menu-button" id="menuButton" type="button" aria-label="Mở menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>
  </header>

  <main id="top">
    <section class="hero" id="gioi-thieu">
      <div class="hero-bg"></div>
      <div class="hero-shade"></div>
      <div class="hero-grid"></div>
      <div class="hero-glow"></div>
      <div class="container">
        <div class="hero-content">
          <div class="hero-kicker"><span class="live-dot"></span> Máy chủ đang hoạt động</div>
          <h1>Đánh thức <span>sức mạnh</span></h1>
          <p class="hero-copy">
            Bước vào thế giới Ngọc Rồng, luyện tập cùng những chiến binh huyền thoại,
            săn boss và chinh phục giới hạn sức mạnh của chính bạn.
          </p>
          <div class="hero-actions">
            <a class="button button-primary" href="#vu-tru">Bắt đầu hành trình <span class="button-arrow">→</span></a>
            <a class="button button-secondary" href="#tinh-nang">Khám phá thế giới</a>
          </div>
          <div class="hero-stats">
            <div class="stat"><strong>02</strong><span>Vũ trụ</span></div>
            <div class="stat"><strong>24/7</strong><span>Phiêu lưu</span></div>
            <div class="stat"><strong>Free</strong><span>Trải nghiệm</span></div>
          </div>
        </div>
      </div>
      <span class="scroll-label">Cuộn để khám phá</span>
    </section>

    <section class="section servers" id="vu-tru">
      <div class="container">
        <div class="section-heading" data-reveal>
          <div>
            <span class="eyebrow">Chọn máy chủ</span>
            <h2>Top 1 Đang Chờ Bạn</h2>
          </div>
          <p>Mỗi vũ trụ là một hành trình riêng. Chọn nơi bắt đầu, tạo chiến binh và viết nên câu chuyện của bạn.</p>
        </div>
        <div class="server-grid">
          <article class="server-card" id="serverOne" tabindex="0" role="button" aria-label="Vào Vũ Trụ 1" data-reveal>
            <img class="server-image" src="img/112.png" alt="Nhân vật Vũ Trụ 1">
            <div class="server-info">
              <span class="status">Đang online</span>
              <h3>Vũ Trụ 1 <span>NRO Shadow</span></h3>
              <p>Ngọn lửa Đê Man đã thức tỉnh.</p>
              <span class="server-enter">Vào máy chủ <b>→</b></span>
            </div>
          </article>
          <article class="server-card locked" aria-label="Vũ Trụ 2 đang phát triển" data-reveal>
            <img class="server-image" src="img/113.png" alt="Nhân vật Vũ Trụ 2">
            <div class="server-info">
              <span class="status">Đang phát triển</span>
              <h3>Vũ Trụ 2 <span>Sắp ra mắt</span></h3>
              <p>Mở cửa vào sinh nhật Vũ Trụ 1.</p>
              <span class="server-enter">Chưa thể truy cập</span>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="section" id="tinh-nang">
      <div class="container feature-layout">
        <div class="feature-visual" data-reveal>
          <div class="visual-card">
            <strong>Một thế giới không giới hạn</strong>
            <p>Từ Đảo Kame đến những hành tinh xa xôi, mọi vùng đất đều ẩn chứa thử thách mới.</p>
          </div>
        </div>
        <div class="feature-copy" data-reveal>
          <span class="eyebrow">Trải nghiệm</span>
          <h2>Trở thành chiến binh huyền thoại</h2>
          <p>Không chỉ là những trận chiến. Đây là hành trình trưởng thành, kết nối đồng đội và vượt qua mọi giới hạn.</p>
          <div class="feature-list">
            <div class="feature-item">
              <span class="feature-number">01</span>
              <div><h3>Rèn luyện sức mạnh</h3><p>Nâng cấp chỉ số, học tuyệt kỹ và tìm ra lối chơi phù hợp với chiến binh của bạn.</p></div>
            </div>
            <div class="feature-item">
              <span class="feature-number">02</span>
              <div><h3>Chinh phục thử thách</h3><p>Săn boss, khám phá bản đồ và tham gia những cuộc đối đầu kịch tính.</p></div>
            </div>
            <div class="feature-item">
              <span class="feature-number">03</span>
              <div><h3>Đồng hành cùng bạn bè</h3><p>Gia nhập bang hội, xây dựng đội ngũ và cùng nhau tạo nên những kỷ niệm đáng nhớ.</p></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="section community" id="cong-dong">
      <div class="container">
        <div class="community-head" data-reveal>
          <span class="eyebrow">Cộng đồng là trung tâm</span>
          <h2>Chơi công bằng.<br>Gắn kết dài lâu.</h2>
          <p>Dự án được tạo ra để học tập, nghiên cứu và chia sẻ niềm đam mê Ngọc Rồng với mọi người chơi.</p>
        </div>
        <div class="values" data-reveal>
          <article class="value">
            <span class="value-icon">01</span>
            <h3>Công bằng</h3>
            <p>Mọi người chơi đều được chào đón và có cơ hội phát triển bằng nỗ lực của chính mình.</p>
          </article>
          <article class="value">
            <span class="value-icon">02</span>
            <h3>Ổn định</h3>
            <p>Mỗi vũ trụ được kiểm tra kỹ lưỡng để mang lại trải nghiệm mượt mà và đáng tin cậy.</p>
          </article>
          <article class="value">
            <span class="value-icon">03</span>
            <h3>Không mua bán</h3>
            <p>Trải nghiệm hướng tới cộng đồng, học tập và giải trí. Tất cả đều miễn phí.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="cta">
      <div class="container cta-content" data-reveal>
        <span class="eyebrow">Sẵn sàng chiến đấu?</span>
        <h2>Huyền thoại bắt đầu từ hôm nay</h2>
        <p>Chọn vũ trụ, tạo nhân vật và bắt đầu hành trình chinh phục thế giới Ngọc Rồng.</p>
        <a class="button button-primary" href="#vu-tru">Chọn vũ trụ <span class="button-arrow">→</span></a>
      </div>
    </section>
  </main>

  <footer>
    <div class="container footer-inner">
      <p>© 2026 Ngọc Rồng Online. Dự án học tập và giải trí.</p>
      <div class="footer-links">
        <a href="privacy-policy.php">Chính sách bảo mật</a>
        <a href="#top">Lên đầu trang ↑</a>
      </div>
    </div>
  </footer>

  <div class="modal" id="serverModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal-card">
      <div class="modal-top">
        <img class="modal-icon" src="img/112.png" alt="">
        <div>
          <h3 id="modalTitle">Vũ Trụ 1</h3>
          <p>● Máy chủ đang online</p>
        </div>
      </div>
      <p class="modal-copy">Bạn sắp bước vào <span class="server-highlight">NRO SHADOW</span>. Hãy chuẩn bị tinh thần chiến binh và bắt đầu hành trình.</p>
      <div class="modal-actions">
        <button id="closeModal" type="button">Quay lại</button>
        <a href="sv1/trangchu">Vào máy chủ →</a>
      </div>
    </div>
  </div>

  <script>
    const body = document.body;
    const header = document.getElementById("header");
    const menuButton = document.getElementById("menuButton");
    const modal = document.getElementById("serverModal");
    const serverOne = document.getElementById("serverOne");
    const closeModal = document.getElementById("closeModal");

    function toggleMenu(force) {
      const open = typeof force === "boolean" ? force : !body.classList.contains("menu-open");
      body.classList.toggle("menu-open", open);
      menuButton.setAttribute("aria-expanded", String(open));
    }

    function openServer() {
      modal.classList.add("open");
      body.style.overflow = "hidden";
      closeModal.focus();
    }

    function closeServer() {
      modal.classList.remove("open");
      body.style.overflow = "";
      serverOne.focus();
    }

    menuButton.addEventListener("click", () => toggleMenu());
    document.querySelectorAll(".nav-links a").forEach(link => link.addEventListener("click", () => toggleMenu(false)));
    window.addEventListener("scroll", () => header.classList.toggle("scrolled", window.scrollY > 24), { passive: true });

    serverOne.addEventListener("click", openServer);
    serverOne.addEventListener("keydown", event => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        openServer();
      }
    });
    closeModal.addEventListener("click", closeServer);
    modal.addEventListener("click", event => {
      if (event.target === modal) closeServer();
    });
    document.addEventListener("keydown", event => {
      if (event.key === "Escape" && modal.classList.contains("open")) closeServer();
    });

    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: .12 });

    document.querySelectorAll("[data-reveal]").forEach(element => observer.observe(element));
  </script>
</body>
</html>
