// ===== MOON VOTE — Premium Script =====
(function() {
  'use strict';

  const DEX_API = 'https://api.dexscreener.com/latest/dex/tokens/';

  // ─── FETCH TOKEN DATA ───
  async function fetchTokenData(ca) {
    try {
      const res = await fetch(DEX_API + ca);
      if (!res.ok) throw new Error('API error');
      const data = await res.json();
      if (!data.pairs || !data.pairs.length) return null;

      let best = null;
      for (const p of data.pairs) {
        if (p.baseToken && p.baseToken.address === ca) {
          if (!best || (p.volume && p.volume.h24 > (best.volume ? best.volume.h24 : 0))) best = p;
        }
      }
      if (!best) best = data.pairs[0];
      const t = best.baseToken || {};
      const info = best.info || {};
      return { name: t.name || 'Unknown', ticker: t.symbol || 'N/A', image: info.imageUrl || '', ca: ca };
    } catch (e) {
      console.error('DexScreener error:', e);
      return null;
    }
  }

  // ─── PARTICLES ───
  function initParticles() {
    const c = document.getElementById('particles');
    if (!c) return;
    const colors = ['rgba(246,49,252,0.3)', 'rgba(59,255,158,0.2)', 'rgba(255,136,255,0.2)', 'rgba(255,255,255,0.1)'];
    for (let i = 0; i < 30; i++) {
      const p = document.createElement('div');
      p.className = 'particle';
      const s = Math.random() * 4 + 1;
      const col = colors[~~(Math.random() * colors.length)];
      p.style.cssText = 'width:' + s + 'px;height:' + s + 'px;background:' + col + ';left:' + (Math.random()*100) + '%;animation-duration:' + (Math.random()*20+15) + 's;animation-delay:' + (Math.random()*20) + 's;box-shadow:0 0 ' + (s*3) + 'px ' + col;
      c.appendChild(p);
    }
  }

  // ─── SCROLL ANIM ───
  function initScrollAnims() {
    const obs = new IntersectionObserver((entries) => {
      entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('anim-visible'); obs.unobserve(e.target); } });
    }, { threshold: 0.1 });
    document.querySelectorAll('.informations_block').forEach(el => obs.observe(el));
  }

  // ─── SHOW VOTE PAGE ───
  async function loadAndShowVotePage(ca) {
    const loadingOverlay = document.getElementById('loadingOverlay');
    const landingPage = document.getElementById('landingPage');
    const votePage = document.getElementById('votePage');
    const errorState = document.getElementById('errorState');
    const introSection = document.getElementById('introSection');
    const infoSection = document.getElementById('infoSection');

    // Show loading
    if (loadingOverlay) loadingOverlay.style.display = 'flex';
    if (landingPage) landingPage.style.display = 'none';

    // Fetch data
    const data = await fetchTokenData(ca);

    if (!data) {
      if (loadingOverlay) loadingOverlay.style.display = 'none';
      if (votePage) votePage.style.display = 'block';
      if (errorState) {
        errorState.style.display = 'block';
        document.getElementById('errorCA').textContent = ca;
      }
      if (introSection) introSection.style.display = 'none';
      if (infoSection) infoSection.style.display = 'none';
      return;
    }

    // Set COIN data
    COIN.name = data.name;
    COIN.ticker = data.ticker;
    COIN.image = data.image;
    COIN.ca = data.ca;
    COIN.loaded = true;
    COIN.yes = Math.floor(Math.random() * 500) + 500;
    COIN.no = Math.floor(Math.random() * 150) + 50;

    // Preload image before showing
    if (COIN.image) {
      await new Promise((resolve) => {
        const img = new Image();
        img.onload = resolve;
        img.onerror = resolve;
        img.src = COIN.image;
        setTimeout(resolve, 3000); // max wait 3s
      });
    }

    // Show vote page, hide loading
    if (votePage) votePage.style.display = 'block';
    if (loadingOverlay) {
      loadingOverlay.classList.add('hidden');
      setTimeout(() => { if (loadingOverlay.parentNode) loadingOverlay.remove(); }, 600);
    }

    // Apply everything
    applyConfig();
    setupVoteAnim();
    setupModals();
    setupInfoModals();
    setupCopy();
    setupActivity();
    setupStickyBar();
    initScrollAnims();
  }

  // ─── LANDING PAGE LOGIC ───
  function setupLandingPage() {
    const input = document.getElementById('caInput');
    const submit = document.getElementById('caSubmit');
    const wrap = document.querySelector('.landing-input-wrap');
    const example = document.getElementById('exampleCA');

    function handleSubmit() {
      const val = (input.value || '').trim();
      if (!val || !/^(0x)?[A-Za-z0-9]{20,}$/.test(val)) {
        if (wrap) { wrap.classList.add('landing-input-error'); setTimeout(() => wrap.classList.remove('landing-input-error'), 600); }
        if (input) input.focus();
        return;
      }
      // Navigate to the CA URL
      const base = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '');
      window.location.href = base + '/#/' + val;
      // If hash routing, manually trigger load
      COIN.ca = val;
      COIN.hasCA = true;
      loadAndShowVotePage(val);
    }

    if (submit) submit.addEventListener('click', handleSubmit);
    if (input) input.addEventListener('keydown', (e) => { if (e.key === 'Enter') handleSubmit(); });
    if (example) example.addEventListener('click', () => {
      if (input) input.value = example.textContent;
      input.focus();
    });
  }

  // ─── DOM READY ───
  document.addEventListener('DOMContentLoaded', function() {
    initParticles();

    if (COIN.hasCA) {
      // CA in URL → load the vote page
      loadAndShowVotePage(COIN.ca);
    } else {
      // No CA → show landing page
      document.getElementById('landingPage').style.display = 'flex';
      document.getElementById('loadingOverlay').style.display = 'none';
      setupLandingPage();
    }
  });

  // ─── APPLY CONFIG ───
  function applyConfig() {
    const $ = (id) => document.getElementById(id);
    const $$ = (sel) => document.querySelectorAll(sel);

    if ($('contractAddress')) $('contractAddress').value = COIN.ca;
    if ($('yesVotes')) $('yesVotes').textContent = COIN.yes;
    if ($('noVotes')) $('noVotes').textContent = COIN.no;
    updateProgress(COIN.yes, COIN.no);

    document.title = (COIN.ticker || 'Token') + ' – Vote to list on Moonshot';

    const ogT = $('ogTitle'); if (ogT) ogT.content = 'Vote ' + COIN.name + ' to list on Moonshot';
    const ogD = $('ogDesc'); if (ogD) ogD.content = 'Vote YES for ' + COIN.name + ' — earn ' + COIN.xpPerVote + ' XP per vote.';
    const ogI = $('ogImage'); if (ogI && COIN.image) ogI.content = COIN.image;
    const mD = $('metaDesc'); if (mD) mD.content = 'Vote YES for ' + COIN.name + '. Earn XP on Moonshot.';

    const label = COIN.name + (COIN.ticker !== COIN.name ? ' (' + COIN.ticker + ')' : '');
    if ($('coinName')) $('coinName').textContent = label;
    if ($('mainTitle')) $('mainTitle').innerHTML = 'Vote <span>' + (COIN.ticker || 'Token') + '</span> to list on Moonshot';

    const img = $('tokenImage');
    if (img && COIN.image) { img.src = COIN.image; img.style.display = 'block'; }
    else if (img) img.style.display = 'none';

    const bb = $('benefitBanner');
    if (bb) {
      const t = COIN.ticker || 'Token';
      bb.innerHTML = 'Vote YES — earn <span>' + COIN.xpPerVote + ' XP</span> + potential <span class="benefit_ticker">' + t + '</span> rewards & early access when listed.';
    }

    const pc = $('progressCaption');
    if (pc) pc.textContent = 'Skeptics vote NO. Your YES gets ' + (COIN.ticker || 'this token') + ' on Moonshot.';

    const by = $('buttonYes');
    if (by) by.textContent = '🚀 Vote YES — earn ' + COIN.xpPerVote + ' XP';

    $$('.modalCoinName').forEach(el => el.textContent = label);
    $$('.modalCoinAddress').forEach(el => el.textContent = COIN.ca);
    $$('.modalXpAmount').forEach(el => el.textContent = COIN.xpPerVote);
    $$('.modalCoinNameText').forEach(el => el.textContent = label);
    $$('.modalCoinLogo').forEach(el => { if (COIN.image) el.src = COIN.image; });

    const wl = document.querySelector('.informations_rect_lists');
    if (wl && !document.getElementById('whyApple')) {
      const li = document.createElement('li');
      li.className = 'informations_rect_list'; li.id = 'whyApple';
      li.innerHTML = 'If <span>' + (COIN.ticker || 'this token') + '</span> gets listed, anyone can buy it with Apple Pay.';
      wl.appendChild(li);
    }
  }

  // ─── PROGRESS ───
  function updateProgress(yes, no) {
    const total = yes + no;
    const pct = total > 0 ? (yes / total) * 100 : 50;
    const rect = document.querySelector('.intro_block_progress_rect');
    if (rect) rect.style.setProperty('--yes-pct', pct + '%');
    const pt = document.getElementById('progressText');
    if (pt) pt.textContent = pct >= 100 ? 'Ready for review' : (100 - pct).toFixed(0) + '% to listing threshold';
  }

  // ─── VOTE ANIM ───
  function setupVoteAnim() {
    let cy = COIN.yes, cn = COIN.no;
    setInterval(() => {
      cy += Math.random() > 0.7 ? 1 : 0;
      cn += Math.random() > 0.85 ? -1 : 0;
      if (cy > COIN.yes + 15) cy = COIN.yes + 15;
      if (cn < COIN.no - 5) cn = COIN.no - 5;
      const ye = document.getElementById('yesVotes');
      const ne = document.getElementById('noVotes');
      if (ye) ye.textContent = Math.round(cy);
      if (ne) ne.textContent = Math.round(cn);
      updateProgress(cy, cn);
    }, 7000);
  }

  // ─── MODALS ───
  function setupModals() {
    const open = (m) => { if (m) m.style.display = 'block'; };
    const close = (m) => { if (m) m.style.display = 'none'; };
    const my = document.getElementById('modalYes');
    const mn = document.getElementById('modalNo');
    document.getElementById('buttonYes')?.addEventListener('click', () => open(my));
    document.getElementById('buttonNo')?.addEventListener('click', () => open(mn));
    [my, mn].forEach(m => {
      if (!m) return;
      m.querySelector('.modal_rect_close')?.addEventListener('click', () => close(m));
      m.querySelector('.modal_overlay')?.addEventListener('click', () => close(m));
      m.querySelectorAll('[id*="Cancel"]').forEach(b => b.addEventListener('click', () => close(m)));
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        close(my); close(mn);
        close(document.getElementById('modalAbout'));
        close(document.getElementById('modalRewards'));
      }
    });
  }

  // ─── INFO MODALS ───
  function setupInfoModals() {
    const open = (m) => { if (m) m.style.display = 'block'; };
    const close = (m) => { if (m) m.style.display = 'none'; };
    const ma = document.getElementById('modalAbout');
    const mr = document.getElementById('modalRewards');
    document.getElementById('btnAbout')?.addEventListener('click', () => open(ma));
    document.getElementById('btnRewards')?.addEventListener('click', () => open(mr));
    [ma, mr].forEach(m => {
      if (!m) return;
      m.querySelector('.modal_rect_close')?.addEventListener('click', () => close(m));
      m.querySelector('.modal_overlay')?.addEventListener('click', () => close(m));
    });
  }

  // ─── COPY ───
  function setupCopy() {
    const area = document.getElementById('copyArea');
    const notif = document.getElementById('copyNotif');
    if (!area) return;
    area.addEventListener('click', () => {
      const val = document.getElementById('contractAddress')?.value || '';
      navigator.clipboard.writeText(val).then(() => {
        if (notif) { notif.classList.add('show'); setTimeout(() => notif.classList.remove('show'), 1500); }
      }).catch(e => console.error('Copy failed:', e));
    });
  }

  // ─── ACTIVITY ───
  function setupActivity() {
    const container = document.getElementById('recentActivity');
    if (!container) return;
    const chars = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    const randAddr = () => { let a=''; for(let i=0;i<4;i++) a+=chars[~~(Math.random()*chars.length)]; a+='...'; for(let i=0;i<4;i++) a+=chars[~~(Math.random()*chars.length)]; return a; };
    const coinLabel = () => COIN.name + (COIN.ticker !== COIN.name ? ' (' + COIN.ticker + ')' : '');
    const logo = () => (COIN.image || 'assets_img_modal_rect_logo.png');

    function makeItem() {
      const vote = Math.random() > 0.45 ? 'YES' : 'NO';
      const pos = vote === 'YES';
      const el = document.createElement('div');
      el.className = 'informations_rect_item';
      el.innerHTML = '<div class="informations_rect_item_content"><img src="'+logo()+'" alt="" class="informations_rect_item_logo" onerror="this.style.display=\'none\'"><div class="informations_rect_item_texts"><p class="informations_rect_item_texts_adress">'+randAddr()+' voted <span class="informations_rect_item_texts_'+vote.toLowerCase()+'">'+vote+'</span></p><p class="informations_rect_item_texts_text">For '+coinLabel()+'</p></div><p class="informations_rect_item_vote informations_rect_item_vote_'+(pos?'green':'red')+'">'+(pos?'+1':'-1')+'</p></div>';
      return el;
    }

    for (let i = 0; i < 4; i++) container.appendChild(makeItem());
    setInterval(() => {
      const f = container.querySelector('.informations_rect_item');
      if (f) { f.style.opacity='0'; f.style.transform='translateX(-20px)'; setTimeout(()=>f.remove(), 400); }
      setTimeout(() => {
        const ni = makeItem();
        ni.style.opacity='0'; ni.style.transform='translateY(10px)';
        ni.style.transition='all 0.5s cubic-bezier(0.16,1,0.3,1)';
        container.appendChild(ni);
        requestAnimationFrame(() => { ni.style.opacity='1'; ni.style.transform='translateY(0)'; });
      }, 300);
    }, 4000);
  }

  // ─── STICKY CTA ───
  function setupStickyBar() {
    const btn = document.getElementById('stickyVoteBtn');
    const my = document.getElementById('modalYes');
    if (btn && my) {
      btn.textContent = '🚀 Vote YES — earn ' + (COIN.xpPerVote || 25) + ' XP';
      btn.addEventListener('click', () => { my.style.display = 'block'; });
    }
    document.body.classList.add('has_sticky_cta');
  }

  // ─── PARALLAX ───
  const money = document.querySelector('.intro_block_money');
  if (money) {
    let tx=0, ty=0, cx=0, cy=0, tick=false;
    const upd = () => { cx+=(tx-cx)*0.1; cy+=(ty-cy)*0.1; money.style.transform='translate3d('+cx.toFixed(1)+'px,'+cy.toFixed(1)+'px,0)'; if(Math.abs(cx-tx)>0.1||Math.abs(cy-ty)>0.1) requestAnimationFrame(upd); else tick=false; };
    window.addEventListener('mousemove', e => { tx=-(e.clientX/innerWidth-0.5)*60; ty=-(e.clientY/innerHeight-0.5)*30; if(!tick){tick=true;requestAnimationFrame(upd);} });
    window.addEventListener('mouseleave', () => { tx=0;ty=0; if(!tick){tick=true;requestAnimationFrame(upd);} });
  }

})();