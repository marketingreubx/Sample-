/* Smart Writer + Tracker for the Cold Outreach System. State lives in user meta (ir_pb_trk), details in user meta (ir_rw_profile). */
(function () {
  var C = window.PBT; if (!C) return;
  var W = window.IRSW || {};
  var S = C.state || (C.state = {});
  S.goal = S.goal || 15; S.me = (S.me && !Array.isArray(S.me)) ? S.me : {}; S.days = (S.days && !Array.isArray(S.days)) ? S.days : {}; S.leads = S.leads || [];
  try { S.tz = Intl.DateTimeFormat().resolvedOptions().timeZone || S.tz; } catch (e) { }

  /* ---------- helpers ---------- */
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return [].slice.call((r || document).querySelectorAll(s)); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function today() { return ymd(new Date()); }
  function addDays(s, n) { var p = s.split('-'); var d = new Date(+p[0], +p[1] - 1, +p[2]); d.setDate(d.getDate() + n); return ymd(d); }
  function nice(s) { if (!s) return ''; var t = today(); if (s === t) return 'Today'; if (s === addDays(t, 1)) return 'Tomorrow'; if (s === addDays(t, -1)) return 'Yesterday'; var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }); }
  function day(d) { d = d || today(); if (!S.days[d]) S.days[d] = { dm: 0, em: 0, fu: 0, rep: 0, call: 0, cl: 0 }; return S.days[d]; }
  function bump(k, n) { var d = day(); d[k] = Math.max(0, (d[k] || 0) + (n || 1)); }
  var saveT = null;
  var QUIET = false;
  function save(quiet) {
    QUIET = !!quiet;
    clearTimeout(saveT);
    saveT = setTimeout(function () {
      var f = new FormData(); f.append('action', 'ir_pb_trk'); f.append('n', C.n); f.append('data', JSON.stringify(S));
      fetch(C.aj, { method: 'POST', body: f, credentials: 'same-origin' }).then(function () { if (!QUIET) { flash('Saved'); } QUIET = false; }).catch(function () { flash('Not saved. Check your connection.'); });
    }, 500);
  }
  var toastT;
  function flash(t) { var el = $('#pbToast'); if (!el) { el = document.createElement('div'); el.id = 'pbToast'; el.className = 'pbt-toast'; document.body.appendChild(el); } el.textContent = t; el.classList.add('on'); clearTimeout(toastT); toastT = setTimeout(function () { el.classList.remove('on'); }, 2200); }
  function copyText(t, done) {
    LASTSENT = String(t || '');
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t).then(done, done); }
    else { var a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select(); try { document.execCommand('copy'); } catch (e) { } a.remove(); done(); }
  }

  /* ---------- text helpers ---------- */
  function clean(v) { return String(v || '').replace(/\s+/g, ' ').trim(); }
  function nodot(v) { return v.replace(/[.!?,;:\s]+$/, ''); }
  function cap1(v) { return v ? v.charAt(0).toUpperCase() + v.slice(1) : v; }
  function sentence(v) { v = clean(v); if (!v) return v; v = cap1(v); return /[.!?…)"'”’]$/.test(v) ? v : v + '.'; }
  var PROPER = /^(Instagram|TikTok|Facebook|WhatsApp|LinkedIn|YouTube|Twitter|Pinterest|Snapchat|Google|Shopify|WordPress|Canva|Lagos|Abuja|Nigeria|Nigerian|Ghana|Ghanaian|Kenya|Kenyan|African|Africa|Black|British|American|London|Dubai|Reels?|Stories|Story)\b/;
  function lc1(v) {
    var w = v.split(/\s/)[0];
    if (!v || /^I(\b|['’])/.test(v) || PROPER.test(v) || /[A-Z].*[A-Z]/.test(w) || /^\d/.test(w)) return v;
    return v.charAt(0).toLowerCase() + v.slice(1);
  }
  function article(v) {
    var w = v.split(/\s+/)[0], an;
    if (/^[A-Z]{2,}$/.test(w)) an = /^[AEFHILMNORSX]/.test(w);
    else an = /^(hour|honest|honou?r|heir)/i.test(w) || (/^[aeiou]/i.test(w) && !/^(uni|use|usu|usa|eu|one|once|ubi)/i.test(w));
    return (an ? 'an ' : 'a ') + v;
  }
  function stripArt(v) { return v.replace(/^(a|an|the)\s+/i, ''); }
  function stripTo(v) { return v.replace(/^(to|and|so (that )?(you|they) can|in order to)\s+/i, ''); }
  function stripThat(v) { return v.replace(/^that\s+/i, ''); }
  function platformCase(v) { return v.replace(/\b(instagram|tiktok|whatsapp|facebook|linkedin|youtube|twitter|pinterest|snapchat)\b/gi, function (m) { return { instagram: 'Instagram', tiktok: 'TikTok', whatsapp: 'WhatsApp', facebook: 'Facebook', linkedin: 'LinkedIn', youtube: 'YouTube', twitter: 'Twitter', pinterest: 'Pinterest', snapchat: 'Snapchat' }[m.toLowerCase()]; }); }

  /* ---------- tracker rules ---------- */
  var STAGES = [
    ['sent', 'Sent', '#1660E8'], ['seen', 'Seen, no reply', '#7C3AED'], ['ghost', 'Ghosted', '#64748B'], ['replied', 'Replied', '#0891B2'],
    ['interested', 'Interested', '#CA8A04'], ['call', 'Call booked', '#FF7A1A'], ['client', 'Client 🎉', '#16A34A'], ['no', 'Not interested', '#94A3B8']
  ];
  var STAGE = {}; STAGES.forEach(function (s) { STAGE[s[0]] = s; });
  var FU_DM = [2, 5, 7, 30], FU_EM = [3, 7, 14, 30];
  var FU_DM_NAME = ['Follow-up 1 · after 2 days', 'Follow-up 2 · after 5 days', 'Follow-up 3 · after 7 days', 'Follow-up 4 · after a month'];
  var FU_EM_NAME = ['Follow-up 1 · after 3 days', 'Follow-up 2 · after 7 days', 'Follow-up 3 · after 14 days', 'Follow-up 4 · after a month'];
  function isEmail(l) { return l.ch === 'email'; }
  function fuDays(l) { return isEmail(l) ? FU_EM : FU_DM; }
  function fuName(l) { return (isEmail(l) ? FU_EM_NAME : FU_DM_NAME)[l.fu || 0] || ''; }
  function nextFor(l) { if (l.st !== 'sent' && l.st !== 'seen') return ''; var i = l.fu || 0, d = fuDays(l); return i < d.length ? addDays(l.sent, d[i]) : ''; }
  function who(l) { return (isEmail(l) || l.at === false) ? l.h : '@' + l.h; }
  function addLead(o) {
    var l = { id: Date.now().toString(36) + Math.random().toString(36).slice(2, 6), h: String(o.h || '').replace(/^@/, '').trim(), pl: o.pl || 'Instagram', st: 'sent', sent: today(), fu: 0, ang: o.ang || '', notes: o.notes || '', ch: o.ch || 'dm', at: o.at !== false, name: o.name || '', biz: o.biz || '', obs: o.obs || '', contact: o.contact || {}, like: o.like || '', log: o.log || [] };
    l.next = nextFor(l); S.leads.unshift(l); if (S.leads.length > 600) S.leads.length = 600;
    bump(isEmail(l) ? 'em' : 'dm'); save(); return l;
  }
  function logFollowUp(l) { l.fu = (l.fu || 0) + 1; l.next = nextFor(l); bump('fu'); save(); }
  function setStage(l, st) {
    var order = ['sent', 'seen', 'replied', 'interested', 'call', 'client'];
    var was = order.indexOf(l.st), now = order.indexOf(st);
    if (was < 2 && now >= 2) bump('rep');
    if (was < 4 && now >= 4) bump('call');
    if (was < 5 && now === 5) bump('cl');
    l.st = st; l.next = nextFor(l); l.end = FIN[st] ? (l.end || today()) : ''; save();
  }
  /* Finished prospects (client, not interested, ghosted) leave the active list straight away. After 21 days they shrink to a stub that only keeps the stats. */
  var FIN = { client: 1, no: 1, ghost: 1 };
  function isStub(l) { return !!(l && l.x); }
  function isDone(l) { return !!FIN[l.st]; }
  function tidy() {
    var ch = false, td = today();
    S.leads.forEach(function (l, i) {
      if (isStub(l)) { return; }
      /* Every follow-up sent and still no reply: ghosted. */
      if ((l.st === 'sent' || l.st === 'seen') && (l.fu || 0) >= fuDays(l).length && !l.next) { l.st = 'ghost'; l.end = td; ch = true; }
      if (isDone(l) && !l.end) { l.end = td; ch = true; }
      if (isDone(l) && l.end && l.end <= addDays(td, -21)) { S.leads[i] = { id: l.id, x: 1, st: l.st, ang: l.ang || '', ch: l.ch || 'dm', sent: l.sent || '', end: l.end }; ch = true; }
    });
    return ch;
  }
  function live() { return S.leads.filter(function (l) { return !isStub(l); }); }
  function total(d) { return (d.dm || 0) + (d.em || 0); }
  function streak() { var n = 0, d = today(); if (!(S.days[d] && total(S.days[d]))) d = addDays(d, -1); while (S.days[d] && total(S.days[d]) > 0) { n++; d = addDays(d, -1); } return n; }
  function sum(k, n) { var t = 0, d = today(); for (var i = 0; i < n; i++) { if (S.days[d]) t += S.days[d][k] || 0; d = addDays(d, -1); } return t; }

  /* ---------- the approaches ---------- */
  function cleanBiz(b) { b = clean(String(b || '').split(/\s*[|\u2022\u00b7]\s*|\s+[-\u2013\u2014]\s+/)[0]).replace(/[\u{1F000}-\u{1FFFF}\u{2600}-\u{27BF}]/gu, '').trim(); if (b && b === b.toUpperCase() && /[A-Z]{4,}/.test(b)) { b = b.split(/\s+/).map(function (w) { return w.length <= 3 && /^[A-Z]+$/.test(w) && ['THE', 'AND', 'FOR', 'OF', 'BY'].indexOf(w) < 0 ? w : w.charAt(0) + w.slice(1).toLowerCase(); }).join(' '); } return b; }
  function P0(P, M) {
    var bz = cleanBiz(M.biz) || 'your business';
    return {
      bz: bz, hi: hiFor(M),
      iam: "I'm " + article(P.job),
      help: 'I help ' + P.client + ' ' + P.result + (P.service ? ' through ' + P.service : '') + '.',
      helpShort: 'I help ' + P.client + ' ' + P.result + '.',
      proof: P.proof || '', link: P.link
    };
  }
  /* Nigerian prospects get the greeting they expect: Good morning / afternoon / evening @handle. Others: Hey @handle. */
  var NGRE = /\+?234|\.ng\b|naija|nigeria|lagos|abuja|lekki|ikeja|ikoyi|victoria island|port ?harcourt|ibadan|enugu|owerri|benin city|kano|kaduna|uyo|calabar|asaba|warri|abeokuta|ilorin|jos\b|\u20a6/i;
  function isNG(M) { var g = S.greet || 'auto'; if (g === 'ng') { return true; } if (g === 'std') { return false; } return !!(M && (M.ng || NGRE.test([M.handle, M.biz, M.name, M.where].join(' ')))) || (!M || M.ng !== false) && S.tz === 'Africa/Lagos'; }
  function tod() { var h = new Date().getHours(); return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening'; }
  function hiFor(M) {
    var h = String(M.handle || '').replace(/^@+/, '');
    if (isNG(M)) { return h ? tod() + ' @' + h + ',' : M.name ? tod() + ' ' + M.name + ',' : tod() + ','; }
    return h ? 'Hey @' + h + ',' : (M.name ? 'Hi ' + M.name + ',' : 'Hi there,');
  }
  function ngFix(t, M) { return isNG(M) ? String(t).replace(/^(?:hey|hi|hello)\s+@([\w.]+)[,!]?\s*(?:\u{1F44B}\s*)?/iu, function (m, h) { return tod() + ' @' + h + ', '; }).replace(/^@([\w.]+)\s+hi again!?\s*/i, function (m, h) { return tod() + ' @' + h + ', '; }) : t; }
  function join(a) { return a.filter(function (x) { return x; }).join('\n\n'); }
  function withLink(P, x) { return P.link ? x.slice(0, -1).concat(['You can see my work and book a time here: ' + P.link], x.slice(-1)) : x; }

  var APPROACHES = {
    email: {
      first: [
        { k: 'e-problem', n: 'Problem-Focused', d: 'Point out one real gap and what it costs them. The best all-rounder.', need: ['obs'], f: function (P, M, X) { return { subject: 'quick question about ' + X.bz, body: join(withLink(P, [X.hi, 'I was looking at ' + X.bz + ' and noticed ' + M.obs + '.', (M.impact || 'That usually means people who like what you do never take the next step.'), X.iam + '. ' + X.help + (X.proof ? ' ' + X.proof : ''), 'Worth a quick look? I can send over 3 ideas for ' + X.bz + '.', P.me])) }; } },
        { k: 'e-result', n: 'Result-Oriented', d: 'Lead with a result you got for someone similar.', need: ['obs'], f: function (P, M, X) { return { subject: 'an idea for ' + X.bz, body: join(withLink(P, [X.hi, X.help + (X.proof ? ' ' + X.proof : ''), 'I looked at ' + X.bz + ' and I think the same approach could work for you, especially since I noticed ' + M.obs + '.', 'Want me to send over 3 ideas? No pitch, just ideas.', P.me])) }; } },
        { k: 'e-curious', n: 'Curiosity Question', d: 'One short, easy question. Lowest pressure.', need: ['obs'], f: function (P, M, X) { return { subject: 'quick question', body: join(withLink(P, [X.hi, 'Quick question: I noticed ' + M.obs + '. Is that something you are already working on?', 'I have a couple of ideas that could help. Should I send them over?', X.iam + ' who helps ' + P.client + ' ' + P.result + '.', P.me])) }; } },
        { k: 'e-audit', n: 'Free Mini-Audit', d: 'Offer a free quick audit or video. High reply rate for services.', need: ['obs'], note: 'Record your 2 minute video before you send this, so you can share it the moment they say yes.', f: function (P, M, X) { return { subject: 'free audit for ' + X.bz, body: join(withLink(P, [X.hi, 'I spent 10 minutes going through ' + X.bz + ' and found 3 quick wins. The first one: ' + M.obs + '.', 'I recorded a short video showing exactly what I would change. Want me to send it over?', X.iam + ' who helps ' + P.client + ' ' + P.result + '.', P.me])) }; } },
        { k: 'e-compliment', n: 'Compliment and Gap', d: 'A genuine compliment first, then the one gap.', need: ['obs', 'like'], f: function (P, M, X) { return { subject: 'idea for ' + X.bz, body: join(withLink(P, [X.hi, 'I really like ' + M.like + '. It is clear you care about your work.', 'One thing I noticed: ' + M.obs + '.' + (M.impact ? ' ' + M.impact : ''), X.iam + '. ' + X.help + ' Would a simple plan be useful?', P.me])) }; } },
        { k: 'e-direct', n: 'Direct Offer', d: 'Three short parts. For busy owners.', need: ['obs'], f: function (P, M, X) { return { subject: 'quick chat about ' + X.bz, body: join(withLink(P, [X.hi, X.help + (X.proof ? ' ' + X.proof : ''), 'I looked at ' + X.bz + ' and noticed ' + M.obs + '. I think I can help.', 'Open to a quick chat this week? If now is not the right time, no problem at all.', P.me])) }; } }
      ],
      fu: [
        { k: 'e-fu3', n: 'Follow-up 1 · Day 3', d: 'A short bump with one new detail.', need: [], f: function (P, M, X) { return { subject: 'one more idea for ' + X.bz, body: join([X.hi, M.obs ? 'Quick follow up on my last email about ' + X.bz + '. I noticed ' + M.obs + ', and I can show you how to fix it in about 10 minutes.' : 'Quick follow up on my last email about ' + X.bz + '. I have a couple of ideas for you and can send them over in a few minutes.', 'Would it be helpful if I sent the ideas over?', P.me]) }; }, note: 'Best sent as a reply in the same email thread as your first email.' },
        { k: 'e-fu7', n: 'Follow-up 2 · Day 7', d: 'Show proof or a mini case study.', need: [], f: function (P, M, X) { return { subject: 'an example for ' + X.bz, body: join([X.hi, 'I did not want to leave you without an example.', X.proof ? X.proof : X.help + ' I would be happy to show what that could look like for ' + X.bz + '.', 'Should I send a short breakdown?', P.me]) }; }, note: 'Best sent as a reply in the same email thread as your first email.' },
        { k: 'e-fu14', n: 'Follow-up 3 · Day 14', d: 'A free tip with no pitch.', need: [], tip: true, f: function (P, M, X) { return { subject: 'a free tip for ' + X.bz, body: join([X.hi, 'No pitch this time, just something useful.', M.tip ? M.tip : (M.obs ? 'Here is one quick thing to try: sort out what I mentioned earlier (' + M.obs + '). It is usually the fastest win.' : 'If you would like a quick idea for ' + X.bz + ', just reply and I will send one over.'), 'If you want the rest of my ideas for ' + X.bz + ', I am happy to share them.', P.me]) }; } },
        { k: 'e-fu30', n: 'Follow-up 4 · Day 30', d: 'The polite breakup email. Gets a surprising number of replies.', need: [], f: function (P, M, X) { return { subject: 'should I close your file?', body: join([X.hi, 'I have not heard back, so I will assume the timing is not right and stop emailing you.', 'If you want to ' + P.result + ' later, just reply to this and I will pick it up from there.', 'Wishing ' + X.bz + ' a great season.', P.me]) }; } }
      ]
    },
    dm: {
      first: [
        { k: 'd-problem', n: 'Problem-Focused', d: 'Show you noticed a gap that costs them clients.', need: ['obs'], uses: ['like'], f: function (P, M, X) { return { msgs: [
          ['Message 1', X.hi + ' ' + (M.like ? 'I really like ' + M.like + '.' : 'I came across ' + (M.biz || 'your page') + ' and spent a few minutes going through your posts.')],
          ['Message 2 · send right after', 'But I noticed something that might be quietly costing you clients...\n\n' + sentence(M.obs) + (M.impact ? ' ' + M.impact : '') + '\n\n' + X.help],
          ['Message 3 · send separately', 'Would it be okay if I shared 2 quick things you could change right now to start seeing a difference?']] }; } },
        { k: 'd-result', n: 'Result-Oriented', d: 'Lead with a result you got for someone like them.', need: [], uses: ['like'], f: function (P, M, X) { return { msgs: [
          ['Message 1', X.hi + ' I love ' + (M.like || 'what you are building with ' + X.bz) + '.'],
          ['Message 2 · send separately', X.proof ? 'I recently worked with someone just like you.\n\n' + X.proof : X.help],
          ['Message 3 · send separately', 'I believe the same is possible for you. Would you like me to show you exactly how?']] }; } },
        { k: 'd-value', n: 'Value-Based', d: 'Give something useful first, ask later.', need: ['obs'], f: function (P, M, X) { return { msgs: [
          ['Message 1', X.hi + ' I have been following ' + X.bz + ' for a bit and I noticed ' + M.obs + '.' + (M.impact ? ' ' + M.impact : '')],
          ['Message 2 · send separately', 'I put together ' + (X.link ? 'something free' : 'a quick free idea') + ' specifically for ' + P.client + ' who want to ' + P.result + '.\n\nWould it be okay if I sent it your way? It is completely free, just something I think would genuinely help.']],
          br: [['If they say YES', (X.link ? 'Amazing! Here it is 👇\n' + X.link + '\n\nLet me know what you think once you have had a look. ' : 'Amazing! I will send it over now. ') + 'And if you ever want help putting it into practice, that is exactly what I do.']] }; } },
        { k: 'd-audit', n: 'Compliment and Quick Audit', d: 'A genuine compliment, then a free 3 point audit.', need: ['obs'], uses: ['like'], f: function (P, M, X) { return { msgs: [
          ['Message 1', X.hi + ' ' + (M.like ? 'I love ' + M.like + '!' : 'I love what you are doing with ' + X.bz + '!') + ' You are doing something right.'],
          ['Message 2 · send separately', 'That said, I noticed ' + M.obs + '.\n\n' + (M.impact || 'It is a small tweak but it could make a real difference in how many people reach out to you.')],
          ['Message 3 · send separately', X.iam + ' and I specialise in helping ' + P.client + ' ' + P.result + '.\n\nCan I put together a quick 3 point audit for ' + X.bz + ', completely free?']] }; } },
        { k: 'd-direct', n: 'The Direct Approach', d: 'Short and clear, for busy people.', need: ['obs'], f: function (P, M, X) { return { msgs: [
          ['Message 1', X.hi + ' I will keep this short because I know your time is valuable.'],
          ['Message 2 · send separately', "I'm " + P.me + ', ' + article(P.job) + ' who specialises in helping ' + P.client + ' ' + P.result + '.\n\nI looked at ' + X.bz + ', noticed ' + M.obs + ', and I genuinely believe I can help.'],
          ['Message 3 · send separately', 'Would you like me to show you what I would change first?']] }; } },
        { k: 'd-soft', n: 'Soft Question Opener', d: 'A gentle question that starts a conversation.', need: [], uses: ['like'], f: function (P, M, X) { return { msgs: [
          ['Message 1', X.hi + ' hope your week is going well. Came across ' + X.bz + ' and was genuinely impressed' + (M.like ? ' by ' + M.like : '') + '.'],
          ['Message 2 · send separately', 'Quick question, are you currently happy with the number of clients or inquiries you are getting right now?\n\nOr do you feel like there is more potential there that has not been tapped yet? Either answer is totally fine, just curious.']],
          br: [['If they say "It could be better"', 'That is actually really common. You are doing great work but the visibility piece just is not converting the way it should.\n\nThat is exactly what I help with. ' + X.iam + ' who works with ' + P.client + ' to ' + P.result + '.\n\nWant me to show you the 2 things I would change first?'], ['If they say "Things are going well"', 'That is great to hear. If you ever feel like you want to scale that further, feel free to reach out. Happy to support that growth when the time is right.']] }; } },
        { k: 'd-niche', n: 'Niche-Expertise Opener', d: 'Show you work only with people like them.', need: ['obs'], f: function (P, M, X) { return { msgs: [
          ['Message 1', X.hi + ' I specialise in working with ' + P.client + ', so when I came across ' + X.bz + ', I immediately noticed a few things.'],
          ['Message 2 · send separately', 'Most ' + P.client + ' I talk to are dealing with similar challenges, and one stood out on ' + X.bz + ': ' + M.obs + '.' + (M.impact ? ' ' + M.impact : '') + '\n\nSound familiar at all?'],
          ['Message 3 · send separately', X.iam + ' and I help ' + P.client + ' solve exactly this' + (P.service ? ' through ' + P.service : '') + '.' + (X.proof ? ' ' + X.proof : '') + '\n\nWould you like to see what that could look like for ' + X.bz + '?']] }; } }
      ],
      fu: [
        { k: 'd-nots', n: 'Not seen · float it back up', d: 'They have not opened your first message.', need: [], f: function (P, M, X) { return { msgs: [['Follow-up', X.hi + '\nJust floating this back to the top in case it got buried, inboxes can be wild!\n\nI reached out because I genuinely think what I do could be a great fit for ' + X.bz + '.\n\n' + X.helpShort + '\n\nWorth a quick chat? No pressure at all. 😊']] }; } },
        { k: 'd-fu2', n: 'After 2 days · light and curious', d: 'A friendly bump.', need: [], f: function (P, M, X) { return { msgs: [['Follow-up', X.hi + ' 👋\nJust bumping this up in case it got lost in the noise.\n\nI reached out because I think there is something here worth a quick conversation. ' + X.helpShort + ' From what I have seen of ' + X.bz + ', I think you would be a great fit.\n\nWould love to connect if you are open to it! 😊']] }; } },
        { k: 'd-fu2t', n: 'After 2 days · lead with a tip', d: 'Give a quick useful tip first.', need: ['tip'], tip: true, f: function (P, M, X) { return { msgs: [['Follow-up', X.hi + ' 👋\nI sent you a message a couple of days ago and just wanted to follow up, no pressure at all!\n\nWhile I am here, here is something quick that has been working well for ' + P.client + ':\n\n' + M.tip + '\n\nSomething to try even before we chat! And if you would like more tailored ideas for ' + X.bz + ', I am here. 😊']] }; } },
        { k: 'd-fu5', n: 'After 5 days · a simple question', d: 'Reframe with a question.', need: [], f: function (P, M, X) { return { msgs: [['Follow-up', X.hi + ' 😊\nI know you are probably juggling a lot, so I will keep this short.\n\nQuick question: is growing ' + X.bz + ' on your radar right now, or is your focus somewhere else entirely?\n\nEither answer is genuinely helpful for me to know, no sales pitch, just curious. 🙂']] }; } },
        { k: 'd-fu5w', n: 'After 5 days · share a win', d: 'Share a real result. Needs your client result.', need: ['proof'], f: function (P, M, X) { return { msgs: [['Follow-up', X.hi + ' 👋\nJust wanted to share something exciting. ' + X.proof + '\n\nIt got me thinking about you and what is possible for ' + X.bz + ' with the right strategy.\n\nStill happy to share what we did, would that be useful? 😊']] }; } },
        { k: 'd-fu7', n: 'After 7 days · graceful exit', d: 'Leaves the door open.', need: [], f: function (P, M, X) { return { msgs: [['Follow-up', X.hi + ' 👋\nI have reached out a few times now and I do not want to keep filling up your inbox, I genuinely respect your time 😊\n\nI will make this my last message for a while. If growing ' + X.bz + ' becomes a priority down the road, I would love to be the one to help you get there.\n\nFeel free to save my contact, the door is always open. Wishing you nothing but growth!']] }; } },
        { k: 'd-fu7t', n: 'After 7 days · leave with value', d: 'A last message with a useful tip.', need: ['tip'], tip: true, f: function (P, M, X) { return { msgs: [['Follow-up', X.hi + '\nLast message from me for now, I promise 😄\n\nBefore I go, I wanted to leave you with something useful:\n\n' + M.tip + '\n\nHope that helps whether or not we work together. If you ever want a full strategy session, I am just a message away. 😊\nTake care!']] }; } },
        { k: 'd-fu30', n: 'After a month · fresh start', d: 'Come back with a new angle.', need: [], f: function (P, M, X) { return { msgs: [['Follow-up', X.hi + ' 👋\nIt has been a little while since I reached out, hope things have been going well on your end! 😊\n\n' + X.helpShort + ' I keep thinking ' + X.bz + ' has a lot of untapped potential.\n\nWould you be open to a quick chat this month? Even just 10 minutes, no commitment at all.']] }; } }
      ]
    }
  };
  function findAp(k) { var out = null; ['email', 'dm'].forEach(function (c) { ['first', 'fu'].forEach(function (t) { APPROACHES[c][t].forEach(function (a) { if (a.k === k) out = a; }); }); }); return out; }

  /* ---------- your details (saved) ---------- */
  var PK = ['name', 'job', 'client', 'result', 'service', 'proof', 'link'];
  function loadProfile() {
    var p = {}; try { p = JSON.parse(localStorage.getItem('irsw') || '{}') || {}; } catch (e) { }
    var s = W.profile || {}, o = {};
    PK.forEach(function (k) { o[k] = clean(s[k] || p[k] || ''); });
    return o;
  }
  var ME = loadProfile();
  function meDone() { return !!(ME.name && ME.job && ME.client && ME.result); }
  function normMe(m) {
    return {
      me: cap1(nodot(clean(m.name))),
      job: stripArt(nodot(clean(m.job)).replace(/^freelance\s+/i, '')).split(' ').map(function (w) { return /^[A-Z]{2,}$/.test(w) ? w : w.toLowerCase(); }).join(' '),
      client: lc1(platformCase(nodot(clean(m.client)))),
      result: lc1(stripTo(nodot(clean(m.result)))),
      service: lc1(nodot(clean(m.service)).replace(/^(by|through|with)\s+/i, '')),
      proof: sentence(m.proof), link: clean(m.link)
    };
  }
  /* "their" becomes "your", and the start of the phrase is fixed so the sentence reads right */
  function you(v) { return v.replace(/\bthemselves\b/gi, 'yourselves').replace(/\b(they're|they are)\b/gi, "you're").replace(/\b(they've|they have)\b/gi, "you've").replace(/\btheirs\b/gi, 'yours').replace(/\btheir\b/gi, 'your').replace(/\bthem\b/gi, 'you').replace(/\bthey\b/gi, 'you'); }
  var KEEP_AS_IS = /^(your|you|you're|you've|youre|no|not|there|a|an|some|that|how|the|what|it|its|this|these|those|most|many|only|just|one|\d+)\b/i;
  function phrase(raw) {
    var v = lc1(nodot(you(clean(raw)))); if (!v) return '';
    v = v.replace(/^(i\s+(really\s+)?(noticed|saw|love|like|loved|admire)\s+)/i, '').replace(/^that\s+/i, '');
    return KEEP_AS_IS.test(v) ? v : 'your ' + v;
  }
  function normPr(r) {
    return { name: cap1(nodot(clean(r.name))), biz: nodot(clean(r.biz)), obs: phrase(r.obs), impact: sentence(r.impact), like: phrase(r.like), tip: sentence(r.tip), handle: ST.ch === 'dm' ? igHandle(r.handle || '') : '', ng: r.ng, where: r.where || '' };
  }
  function digits(v) { return String(v || '').replace(/[^\d]/g, ''); }
  function waOk(v) { var d = digits(v); return d.length >= 8 && d.length <= 15 && d.charAt(0) !== '0'; }
  function emailOk(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(clean(v)); }
  function igHandle(v) { return clean(v).replace(/^https?:\/\/(www\.)?instagram\.com\//i, '').replace(/[\/?#].*$/, '').replace(/^@+/, '').replace(/\s+/g, ''); }

  /* ---------- the writer ---------- */
  var root = $('#pbSmart');
  var ST = { ch: 'email', type: 'first', ap: 'e-problem', step: 1, view: 'wiz' }, PR = { name: '', biz: '', obs: '', impact: '', like: '', tip: '', via: 'wa', contact: '', reply: '', ctx: '', goal: 'book a short call', handle: '', blist: '', bang: 'problem' };
  var LASTSENT = '', LEAD = null, OUT = null, logged = false, meOpen = false, prev = null, SCAN = null, scanOpen = false, SHOTS = [], busyAi = false;
  function isReply() { return ST.type === 'reply'; }
  function isBulk() { return ST.ch === 'dm' && ST.type === 'first' && ST.bulk === '1'; }
  function steps() { return isReply() ? ['Type', 'Their reply', 'Send'] : isBulk() ? ['Type', 'People'] : ['Type', 'Approach', 'About them', 'Send']; }
  function lastStep() { return steps().length; }
  var GOALS = [['book a short call', 'Book a call'], ['answer their question and keep the conversation going', 'Answer and keep going'], ['share my price confidently', 'Share my price'], ['offer a free sample or mini audit', 'Offer a free sample'], ['stay friendly and follow up later', 'Follow up later']];
  try { var sv = JSON.parse(localStorage.getItem('irsw-st') || '{}'); if (sv.ch === 'email' || sv.ch === 'dm') { ST.ch = sv.ch; ST.ap = APPROACHES[ST.ch].first[0].k; } if (sv.via === 'wa' || sv.via === 'ig') { PR.via = sv.via; } } catch (e) { }
  function list() { return APPROACHES[ST.ch][isReply() ? 'first' : ST.type]; }
  function curAp() { var a = findAp(ST.ap); if (!a || list().indexOf(a) < 0) { a = list()[0]; ST.ap = a.k; } return a; }
  function persist() { try { localStorage.setItem('irsw-st', JSON.stringify({ ch: ST.ch, via: PR.via })); } catch (e) { } }
  function pickDefault() { ST.ap = list()[0].k; }
  function needs(a) { return (a.need || []); }
  function usesLike(a) { return (a.need || []).concat(a.uses || []).indexOf('like') > -1; }

  function fld(id, label, hint, val, o) {
    o = o || {};
    var ctl = o.area ? '<textarea id="' + id + '" rows="' + o.area + '" placeholder="' + esc(o.ph || '') + '">' + esc(val) + '</textarea>' : '<input id="' + id + '" value="' + esc(val) + '" placeholder="' + esc(o.ph || '') + '"' + (o.type ? ' type="' + o.type + '"' : '') + ' autocomplete="off">';
    return '<label class="sw-f"><span>' + label + (o.req ? ' <em>required</em>' : '') + '</span>' + ctl + (hint ? '<small>' + hint + '</small>' : '') + '</label>';
  }
  function pre(id, lead, label, hint, val, ph, req) {
    return '<div class="sw-f"><span>' + label + (req ? ' <em>required</em>' : '') + '</span><div class="sw-pre"><b>' + lead + '</b><input id="' + id + '" value="' + esc(val) + '" placeholder="' + esc(ph) + '" autocomplete="off"></div><small class="sw-pv" id="' + id + '-pv">' + hint + '</small></div>';
  }
  function pitch() {
    var m = {}; PK.forEach(function (k) { var e = $('#me-' + k); m[k] = e ? e.value : ME[k]; });
    var n = normMe(m);
    if (!n.job || !n.client || !n.result) { return 'Your pitch will show here as you type.'; }
    return "I'm " + article(n.job) + '. I help ' + n.client + ' ' + n.result + (n.service ? ' through ' + n.service : '') + '.';
  }
  function meHtml() {
    var sum = meDone() ? 'Saved · ' + esc(cap1(ME.name)) + ', ' + esc(normMe(ME).job) : 'Add your details once. Every message uses them.';
    return '<details class="sw-card sw-me" id="swMe"' + (meOpen ? ' open' : '') + '><summary><span class="ic">👤</span><span><b>Your details</b><small>' + sum + '</small></span></summary><div class="sw-body">' +
      '<div class="sw-two">' + fld('me-name', 'Your first name', '', ME.name, { ph: 'Reuben' }) + fld('me-job', 'What you do', '', ME.job, { ph: 'web designer' }) + '</div>' +
      '<div class="sw-two">' + fld('me-client', 'Who you help', 'Plural', ME.client, { ph: 'skincare brands' }) + fld('me-result', 'The result you get them', 'Start with a verb', ME.result, { ph: 'get more booked calls' }) + '</div>' +
      '<p class="sw-pitch" id="swPitch">' + esc(pitch()) + '</p>' +
      '<div class="sw-lab">How to greet people</div><div class="sw-chips" style="margin-bottom:12px">' + [['auto', 'Auto: Good morning @handle for Nigerian pages'], ['ng', 'Always Good morning @handle'], ['std', 'Always Hey @handle']].map(function (g) { return '<button type="button" data-greet="' + g[0] + '" class="' + ((S.greet || 'auto') === g[0] ? 'on' : '') + '">' + g[1] + '</button>'; }).join('') + '</div>' +
      '<details class="sw-more"><summary>More details <small>optional</small></summary><div class="sw-body0">' +
      fld('me-service', 'How you do it', 'Finishes "through ___"', ME.service, { ph: 'clear websites and simple booking pages' }) +
      fld('me-proof', 'One real client result', 'A full sentence. Only write what is true', ME.proof, { ph: 'One clinic went from 6 to 21 bookings a month.' }) +
      fld('me-link', 'Your link', 'Portfolio or booking page', ME.link, { ph: 'https://yourname.com' }) + '</div></details>' +
      '<div class="sw-row"><button type="button" class="sw-btn" id="swSaveMe">Save my details</button><span class="sw-msg" id="swMeMsg"></span></div></div></details>';
  }
  function progHtml() {
    return '<div class="sw-prog' + (isReply() ? ' three' : '') + '">' + steps().map(function (s, i) { var n = i + 1; return '<button type="button" class="' + (n === ST.step ? 'on' : n < ST.step ? 'done' : '') + '" data-go="' + n + '"' + (n > ST.step ? ' disabled' : '') + '><i>' + (n < ST.step ? '✓' : n) + '</i><span>' + s + '</span></button>'; }).join('') + '</div>';
  }
  function stepBody() {
    var a = curAp(), isFu = ST.type === 'fu', h = '';
    if (ST.step === 1) {
      h = '<h3>What are you writing?</h3><div class="sw-seg" data-seg="ch"><button type="button" data-v="email" class="' + (ST.ch === 'email' ? 'on' : '') + '">✉️ Cold email</button><button type="button" data-v="dm" class="' + (ST.ch === 'dm' ? 'on' : '') + '">💬 Cold DM</button></div>' +
        '<div class="sw-lab">Which message?</div><div class="sw-seg sw-seg3" data-seg="type"><button type="button" data-v="first" class="' + (ST.type === 'first' ? 'on' : '') + '">First message</button><button type="button" data-v="fu" class="' + (isFu ? 'on' : '') + '">Follow-up</button><button type="button" data-v="reply" class="' + (isReply() ? 'on' : '') + '">They replied</button></div>' +
        (ST.ch === 'dm' && ST.type === 'first' ? '<div class="sw-lab">How many people?</div><div class="sw-seg" data-seg="bulk"><button type="button" data-v="0" class="' + (isBulk() ? '' : 'on') + '">One person</button><button type="button" data-v="1" class="' + (isBulk() ? 'on' : '') + '">5 at once ⚡</button></div>' : '') +
        '<p class="sw-help">' + (isBulk() ? 'Fastest way to send 100 DMs a day. Paste up to 5 Instagram links or @handles and we find the problems on each page for you. Each message is worded differently and greets them by @handle. Send, tap Next 5, repeat.' : isReply() ? 'Paste what they said. You get 3 ready replies, using the objection scripts in this System.' : ST.ch === 'dm' ? (isFu ? 'One short message to bring the conversation back.' : 'A short sequence of 2 or 3 messages, sent one after the other.') : (isFu ? 'Day 3, 7, 14 and 30 follow-up emails.' : 'One email with a subject line.')) + '</p>';
    } else if (isBulk() && ST.step === 2) {
      h = bulkBody();
    } else if (isReply() && ST.step === 2) {
      h = '<h3>What did they say?</h3>' + (LEAD ? leadHist(LEAD) : leadPick()) +
        fld('rp-reply', 'Their reply', 'Paste their exact words. Add earlier messages too if it helps.', PR.reply, { area: 5, req: 1, ph: 'e.g. Sounds interesting, but how much do you charge?' }) +
        '<div class="sw-gap"></div>' + (LEAD ? '' : '<div class="sw-two">' + fld('pr-name', 'Their first name', 'Optional', PR.name, { ph: 'Amaka' }) + fld('pr-biz', 'Their business', 'Optional', PR.biz, { ph: 'Glow by Amaka' }) + '</div><div class="sw-gap"></div>' +
        fld('rp-ctx', 'What did you send them first?', 'Optional. One line is enough, e.g. I offered a free audit of their booking page.', PR.ctx, { area: 2 }) + '<div class="sw-gap"></div>') +
        '<div class="sw-lab" style="margin-top:4px">What do you want next?</div><div class="sw-chips" data-goal>' + GOALS.map(function (g) { return '<button type="button" data-v="' + esc(g[0]) + '" class="' + (PR.goal === g[0] ? 'on' : '') + '">' + esc(g[1]) + '</button>'; }).join('') + '</div>';
    } else if (isReply()) {
      h = sendBody();
    } else if (ST.step === 2) {
      h = '<h3>Choose your approach</h3><div class="sw-aps">' + list().map(function (x) { return '<button type="button" class="sw-ap' + (x.k === a.k ? ' on' : '') + '" data-ap="' + x.k + '"><b>' + esc(x.n) + '</b><small>' + esc(x.d) + '</small></button>'; }).join('') + '</div>';
    } else if (ST.step === 3) {
      var needObs = needs(a).indexOf('obs') > -1;
      h = '<h3>About them</h3>' + (LEAD ? '<p class="sw-lead">Following up with <b>' + esc(who(LEAD)) + '</b></p>' : scanHtml()) +
        '<div class="sw-two">' + fld('pr-name', 'Their first name', '', PR.name, { ph: 'Amaka' }) + fld('pr-biz', 'Their business or page', '', PR.biz, { ph: 'Glow by Amaka' }) + '</div>' +
        (ST.ch === 'dm' ? '<div class="sw-gap"></div>' + fld('pr-handle', 'Their @handle', 'Optional. Your message greets them by @handle so they notice it.', PR.handle, { ph: '@glowbyamaka' }) : '') +
        pre('pr-obs', 'I noticed your', 'What did you notice?' + (needObs ? '' : ' (optional)'), 'Finish the sentence. Example: website has no way to book a call', PR.obs, 'website has no booking link', needObs) +
        fld('pr-impact', 'How it affects them' + (clean(PR.impact) ? '' : ' (optional)'), 'One sentence to them. The scan fills this in. Example: So people who find you on Explore leave without seeing your results.', PR.impact, { area: 2, ph: 'So people who find you leave without booking.' }) +
        (usesLike(a) ? pre('pr-like', 'I really like your', 'Something you like about them' + (needs(a).indexOf('like') > -1 ? '' : ' (optional)'), 'Example: product formulation', PR.like, 'product formulation', needs(a).indexOf('like') > -1) : '') +
        (a.tip ? fld('pr-tip', 'A quick tip for them', 'One or two sentences you would give them for free', PR.tip, { area: 2, req: needs(a).indexOf('tip') > -1 }) : '');
    } else {
      h = sendBody();
    }
    return h;
  }
  function sendBody() {
    if (ST.ch === 'email') {
      return '<h3>Send it to</h3>' + fld('pr-contact', 'Their email address', 'Optional. Add it to send in one tap, or leave it empty and just copy the message.', PR.contact, { ph: 'name@example.com', type: 'email' });
    }
    return '<h3>Send it on</h3><div class="sw-seg" data-seg="via"><button type="button" data-v="wa" class="' + (PR.via === 'wa' ? 'on' : '') + '">WhatsApp</button><button type="button" data-v="ig" class="' + (PR.via === 'ig' ? 'on' : '') + '">Instagram</button></div>' +
      '<div class="sw-gap"></div>' + (PR.via === 'wa' ? fld('pr-contact', 'Their WhatsApp number', 'With country code and no zero at the start, e.g. 2348012345678. Optional.', PR.contact, { ph: '2348012345678', type: 'tel' }) : fld('pr-contact', 'Their Instagram', 'Handle or profile link. Optional.', PR.contact || (PR.handle ? '@' + igHandle(PR.handle) : ''), { ph: '@glowbyamaka' }));
  }

  /* ---------- prospect analyser ---------- */
  function scanHtml() {
    var r = SCAN && SCAN.data, open = scanOpen || !!r;
    var h = '<details class="sw-scan" id="swScan"' + (open ? ' open' : '') + '><summary><span class="ic">🔍</span><span><b>Analyse their page</b><small>' + (r ? 'Done · tap an issue to use it in your message' : 'Paste their website, Instagram or Google link. We find what to mention.') + '</small></span></summary><div class="sw-body">' +
      '<div class="sw-scan-in"><input id="scUrl" value="' + esc(SCAN ? SCAN.url : '') + '" placeholder="Website, Instagram or Google link" autocomplete="off" inputmode="url"><button type="button" class="sw-btn" id="scGo"' + (busyAi ? ' disabled' : '') + '>' + (r ? 'Again' : 'Analyse') + '</button></div>' +
      '<input type="file" id="scShot" accept="image/*" multiple hidden><button type="button" class="sw-shot" id="scShotB">📸 ' + (SHOTS.length ? SHOTS.length + ' screenshot' + (SHOTS.length > 1 ? 's' : '') + ' added · change' : 'Add screenshots') + '</button>' +
      '<p class="sw-hint">Paste their Instagram, website or Google link and we read it for you. If an account is closed to outside tools, add 1 or 2 screenshots of their profile instead.</p>' +
      '<div class="sw-scan-st" id="scSt"' + (busyAi ? '' : ' hidden') + '><span class="sw-spin"></span><span id="scStT">' + esc(SCAN && SCAN.step || 'Reading their page...') + '</span></div>' +
      '<p class="sw-err" id="scErr"' + (SCAN && SCAN.err ? (/closed to outside tools/.test(SCAN.err) ? ' style="color:inherit;background:rgba(37,99,235,.08);padding:12px 14px;border-radius:12px;font-weight:600"' : '') : ' hidden') + '>' + esc(SCAN && SCAN.err || '') + '</p>';
    if (r) {
      var best = r.best && r.best[ST.ch], bestAp = best && findAp(best);
      h += '<div class="sw-scan-r"><div class="sw-scan-top"><div><b>' + esc(r.business || r.name || 'Their page') + '</b><small>' + esc(r.summary || '') + '</small>' + (r.based ? '<small style="display:block;margin-top:6px;opacity:.75">' + esc(r.based) + '</small>' : '') + '</div><div class="sw-need" title="How much they need help"><i style="--v:' + (r.need * 10) + '%"></i><span>' + r.need + '/10</span><small>need help</small></div></div>' +
        (function () {
          var ct = r.contact || {}, wa = ct.whatsapp ? '+' + String(ct.whatsapp).replace(/^\+/, '') : '', ph = ct.phone || '', bits = [];
          if (ct.email) { bits.push('✉️ <b>' + esc(ct.email) + '</b> <button type="button" class="sw-link" data-cp="' + esc(ct.email) + '">copy</button>'); }
          if (wa) { bits.push('🟢 WhatsApp <b>' + esc(wa) + '</b> <button type="button" class="sw-link" data-cp="' + esc(wa) + '">copy</button>'); }
          if (ph && digits(ph) !== digits(wa)) { bits.push('📞 <b>' + esc(ph) + '</b> <button type="button" class="sw-link" data-cp="' + esc(ph) + '">copy</button>'); }
          if (ct.website) { bits.push('🌐 ' + esc(String(ct.website).replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, ''))); }
          if (bits.length) { return '<p class="sw-scan-like" style="background:rgba(22,163,74,.1)">Contact details found: ' + bits.join(' · ') + '</p>'; }
          return '<p class="sw-scan-like" style="background:rgba(234,88,12,.1)">' + (ST.ch === 'email' ? '✉️ No email on their pages.' : '📞 No phone or email on what we could read.') + ' ' + (r.kind === 'instagram' ? 'Instagram hides the bio from outside tools, so add a screenshot of their profile (we read the bio from it) or paste their website.' : 'Paste their website or link-in-bio link and scan again, or type it in yourself.') + '</p>';
        })() + (r.like ? '<p class="sw-scan-like">💬 Compliment first: <b>I really like ' + esc(phrase(clean(PR.like) || r.like)) + '.</b></p>' : '') + ((r.likes || []).length ? '<div class="sw-lab">What they do well · tap to use it as your compliment</div><div class="sw-chips" style="margin-bottom:12px">' + [r.like].concat(r.likes).map(function (x, i) { return '<button type="button" data-lk="' + i + '" class="' + (clean(PR.like) === x ? 'on' : '') + '">' + esc(x) + '</button>'; }).join('') + '</div>' : '') + '<div class="sw-lab">Pick what to mention</div>' + (r.issues || []).map(function (x, i) {
          var on = SCAN.pick === i;
          return '<button type="button" class="sw-issue' + (on ? ' on' : '') + '" data-iss="' + i + '"><span class="n">' + (on ? '✓' : i + 1) + '</span><span><b>' + esc(x.title) + '</b><small>' + esc(x.detail) + '</small>' + (x.impact ? '<small><b>How it affects them:</b> ' + esc(x.impact) + '</small>' : '') + (x.fix ? '<em>You fix it by: ' + esc(x.fix) + '</em>' : '') + '</span></button>';
        }).join('') +
        (bestAp ? '<div class="sw-scan-best"><span>Best approach: <b>' + esc(bestAp.n) + '</b>' + (r.best.why ? '<small>' + esc(r.best.why) + '</small>' : '') + '</span>' + (bestAp.k !== curAp().k ? '<button type="button" class="sw-btn ghost" id="scUseAp">Use it</button>' : '<i>✓ Using it</i>') + '</div>' : '') +
        '</div>';
    }
    return h + '</div></details>';
  }
  function scanApply(i, fresh) {
    var r = SCAN && SCAN.data; if (!r) { return; }
    readPr();
    var x = (r.issues || [])[i]; if (x) { PR.obs = x.obs; PR.impact = x.impact || ''; SCAN.pick = i; }
    if (!clean(PR.like) && r.like) { PR.like = r.like; }
    if (!clean(PR.tip) && r.tip) { PR.tip = r.tip; }
    if (!clean(PR.name) && r.name) { PR.name = r.name.split(/\s+/)[0]; }
    if (!clean(PR.biz) && r.business) { PR.biz = r.business; }
    var c = r.contact || {};
    if (fresh && ST.ch === 'dm' && PR.via === 'wa' && !clean(PR.contact) && (c.whatsapp || /^\+?234/.test(c.phone || ''))) { PR.contact = digits(c.whatsapp || c.phone); }
    if (fresh) { PR.ng = r.ng; PR.where = [c.phone, c.whatsapp, c.website, r.business].join(' '); if (ST.ch === 'dm' && c.instagram && !clean(PR.handle)) { PR.handle = '@' + c.instagram; } }
    /* A new scan is a new prospect: the email we found replaces the old one. */
    if (fresh && ST.ch === 'email' && c.email) { PR.contact = c.email; }
    if (!clean(PR.contact)) {
      if (ST.ch === 'email' && c.email) { PR.contact = c.email; }
      else if (ST.ch === 'dm' && c.instagram) { PR.via = 'ig'; PR.contact = '@' + c.instagram; }
      else if (ST.ch === 'dm' && c.whatsapp) { PR.via = 'wa'; PR.contact = c.whatsapp; }
    }
  }
  /* What someone in this line of work should look for on a prospect's page. */
  function swCheck(job) {
    var j = String(job || '').toLowerCase();
    if (/web|website|wordpress|shopify|funnel|landing|developer|ux|\bui\b/.test(j)) { return 'no website at all, or only a link list page; the first screen does not say what they do or who for; no clear button to book, buy or contact near the top; not built for phones; slow or heavy page; no prices or services page; no reviews or results shown; no contact form, WhatsApp or phone link; old copyright year; missing page title or description for Google; no analytics or pixel; broken or empty links; weak or stock looking pictures'; }
    if (/video|reel|editor|videograph|youtube|tiktok/.test(j)) { return 'few or no Reels or videos; videos with no hook in the first line; no captions or text on screen; only photos and flyers; nobody talking to camera; no behind the scenes; captions with no call to action; irregular posting; covers that do not match'; }
    if (/design|brand|flyer|graphic|canva|logo/.test(j)) { return 'flyers that look homemade, crowded or made with low quality AI art; no consistent colours or fonts; hard to read text on graphics; no branded carousels; promo posts with no clear offer or price; no logo or a blurry one; covers and highlights that do not match'; }
    if (/\bads?\b|advert|media buy|ppc/.test(j)) { return 'no sign of running ads; offer posts with no clear call to action; no landing page or booking link; no reviews to use as proof; no clear offer or price; no pixel or analytics on the website; no lead form or WhatsApp link'; }
    if (/copy|caption|writer|email/.test(j)) { return 'captions that are one line or only hashtags; no hook in the first line; no call to action in captions; a bio that does not say who they help or how to book; no client stories; offers explained poorly'; }
    return 'no educational Reels or carousels that teach their audience; no well designed promotional flyers or graphics; flyers that look like low quality AI or crowded template art; a bio that does not say who they help, where they are or how to book; no clear call to action; no booking link, or a link that does not go to booking; no trending or relatable content; a feed that is only before and after photos; captions that are one line or only hashtags; captions with no call to action; irregular posting; no faces, voice or behind the scenes; no client reviews shown; no Reels at all';
  }
  function scanRun() {
    var u = clean(($('#scUrl') || {}).value);
    if (!u && !SHOTS.length) { SCAN = { url: '', err: 'Paste their link or add a screenshot first.' }; draw(); return; }
    if (!window.IRAI) { SCAN = { url: u, err: 'Please refresh the page and try again.' }; draw(); return; }
    readPr(); readMe(); busyAi = true; SCAN = { url: u, step: 'Reading their page...' }; scanOpen = true; draw();
    function step(t) { SCAN.step = t; var x = $('#scStT'); if (x) { x.textContent = t; } }
    function swTool(tool, data, onStep) {
      var C = window.IRC || {};
      function post(fd) { fd.append('n', C.n); return fetch(C.aj, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
      var fd = new FormData(); Object.keys(data).forEach(function (k) { fd.append(k, data[k] == null ? '' : data[k]); }); fd.append('action', 'ir_tool_run'); fd.append('tool', tool);
      return post(fd).then(function (r) {
        if (!r || !r.ok) { throw new Error((r && r.err) || 'busy'); }
        var job = r.job, tries = 0;
        return new Promise(function (res, rej) {
          (function poll() {
            if (++tries > 110) { rej(new Error('That took too long. Please try again.')); return; }
            setTimeout(function () {
              var g = new FormData(); g.append('action', 'ir_tool_job'); g.append('job', job);
              post(g).then(function (j) { if (j && j.wait) { if (onStep && j.step) { onStep(j.step); } poll(); return; } if (j && j.ok) { res(j.data); } else { rej(new Error((j && j.err) || 'busy')); } }).catch(function () { poll(); });
            }, tries < 4 ? 1000 : 1800);
          })();
        });
      }).then(function (d) {
        if (!d || !d._device) { return d; }
        return IRAI.ask({ system: d.sys, user: d.user, max: d.max, onStatus: function (s) { if (onStep) { onStep(s); } } }).then(function (t) {
          var f2 = new FormData(); f2.append('action', 'ir_v2_finish'); f2.append('k', d.k); f2.append('raw', t);
          return post(f2).then(function (j) { if (!j || !j.ok) { throw new Error((j && j.err) || 'busy'); } return j.data; });
        });
      });
    }
    var ch = ST.ch, ap = curAp(), canvases = [];
    var viaSrv = !!(u && !SHOTS.length && window.IRC && IRC.aj);
    var facts = viaSrv ? Promise.resolve(null) : (u ? IRAI.post('ir_ct_facts', { url: u }) : Promise.resolve(null));
    facts.then(function (f) {
      if (f && !f.ok && !SHOTS.length) { throw new Error(f.err || 'This page did not open. Add a screenshot instead.'); }
      var texts = [], chain = Promise.resolve();
      SHOTS.forEach(function (file, i) {
        chain = chain.then(function () { step('Reading screenshot ' + (i + 1) + ' of ' + SHOTS.length + '...'); return IRMEDIA.toCanvas(file, 1500).then(function (c) { canvases.push(c); return IRMEDIA.readImage(c).catch(function () { return []; }); }).then(function (b) { texts.push(b.map(function (x) { return x.text; }).join('\n')); }); });
      });
      return chain.then(function () {
        try { IRMEDIA.stopOcr(); } catch (e) { }
        var good = f && f.ok, src = good ? f.facts : '';
        if (texts.length) { src += (src ? '\n\n' : '') + texts.map(function (t, i) { return 'Text read from their screenshot ' + (i + 1) + ':\n' + t; }).join('\n\n'); }
        if (!viaSrv && !clean(src) && !canvases.length) { throw new Error('We could not read anything from that. Try a clearer screenshot.'); }
        var sys = function (seeing) {
          return 'You are a sharp, kind social media strategist helping a freelancer write a cold ' + (ch === 'email' ? 'email' : 'DM') + " that gets a reply. You study the prospect's page and find the specific, visible things that are costing them clients, especially the ones the freelancer's service fixes.\n" +
            (seeing ? 'You can SEE their page in the attached screenshots (stacked top to bottom). Look closely at the profile picture, bio, highlights, the post grid (what each post shows, photos or graphics, faces or no faces, Reels icons, captions you can read), counts, links and any dates.\n\n' : 'You get the text read from their page and a description of what the screenshots show.\n\n') +
            'Rules:\n' +
            '- WHAT TO LOOK FOR: the freelancer fixes things like these, so check for each of them first and report every one you can actually see: ' + swCheck(normMe(ME).job) + '.\n' +
            '- Report only problems you can actually see or prove from what you were given. If you can prove only 1 or 2, return only those. Always fill "like" and "likes" with real things they are doing well.\n' + (seeing ? '' : '- You CANNOT see their pictures or design. Never say anything about how the page or posts look, and never mention the bio, highlights or stories unless the text below lists them.\n') +
            '- Compliment first. "like" finishes the sentence "I really like your ...": a sincere, specific compliment about something real you can see (the quality of their work, a post style, their results, their branding, how they talk to clients). 4 to 12 words, lowercase.\n' +
            '- "likes": 2 or 3 OTHER real things they are clearly doing right (same style and length as "like", each different), so the freelancer can pick the compliment they prefer. Only things you can see.\n' +
            '- Give up to 5 issues, the most costly first. Only things you can actually see or clearly tell from what is visible. Never guess numbers or invent details.\n' +
            '- Never be vague. Do not write "no video content" or "low engagement" on its own. Say exactly what they are not doing or doing wrong, for example "grid is all flat product photos with no Reels of you doing treatments", "bio says what you do but not where you are or how to book", "highlights have no reviews or prices", "link in bio goes to a page with no booking button".\n' +
            '- Each issue has "obs" that finishes "I noticed your ...": the exact thing, 8 to 20 words, lowercase, no full stop, no "your" at the start.\n' +
            '- Each issue has "impact": ONE sentence spoken to them (use you and your) on how it affects them: lost bookings, lost trust, less reach, people leaving. Start with "So" or "That means". 10 to 25 words. No made up numbers.\n' +
            '- Each issue has "fix": what the freelancer would do about it, 1 sentence.\n' +
            '- "tip": one free, practical tip for the first issue that they could do today, 1 or 2 sentences.\n' +
            '- The freelancer picked the approach "' + ap.n + '" (' + ap.d + '). Write the compliment, observations and impact so they fit that approach.\n' +
            '- Also pick the best approach from the list.\n- Plain, warm, friendly English. No em dashes or en dashes.\n\n' +
            'Approaches: d-problem (gap that costs clients), d-result (result for someone similar), d-value (give value first), d-audit (compliment then free audit), d-direct (short and direct), d-soft (soft question), d-niche (only work with people like them); emails: e-problem, e-result, e-curious, e-audit, e-compliment, e-direct.\n\n' +
            'Reply with JSON only: {"name":"owner first name if clearly visible, else empty","business":"business or page name","summary":"2 sentences: who they are and how they come across online","need":7,"like":"...","likes":["...","..."],"issues":[{"title":"short title","detail":"what you saw and what it costs them, 1 or 2 sentences","obs":"...","impact":"...","fix":"..."}],"tip":"...","best":{"dm":"d-problem","email":"e-problem","why":"1 sentence"},"contact":{"email":"","whatsapp":"","instagram":""}}';
        };
        var user = function (extra) { return 'The freelancer:\n' + meLines() + '\n\nThe prospect' + (u ? ' (' + u + ')' : '') + ':\n' + (extra || '') + src.slice(0, 7000); };
        var parse = function (t) { t = String(t || '').replace(/^```(json)?|```$/g, '').trim(); var a = t.indexOf('{'), z = t.lastIndexOf('}'); var j = JSON.parse(t.slice(a, z + 1)); if (!(j.issues || []).length) { throw new Error('busy'); } return j; };
        /* 1. A model that can see the screenshots (when the site has a vision key). */
        var P0 = normMe(ME), seen = viaSrv ? swTool('scan2', { url: u, ch: ch, ap: ap.n + ' (' + ap.d + ')', me_job: P0.job || '', me_client: P0.client || '', me_result: P0.result || '', me_service: P0.service || '' }, step) : !canvases.length || !window.IRC || !IRC.aj ? Promise.reject(new Error('none')) : (function () {
          step('Looking at their page...');
          var W = 720, H = 0, cs = canvases.slice(0, 4);
          cs.forEach(function (c) { H += Math.round(c.height * W / c.width); });
          var k = Math.min(1, 2400 / H), x = document.createElement('canvas'); x.width = Math.round(W * k); x.height = Math.round(H * k);
          var g = x.getContext('2d'), y = 0; g.fillStyle = '#fff'; g.fillRect(0, 0, x.width, x.height);
          cs.forEach(function (c) { var h = Math.round(c.height * W / c.width * k); g.drawImage(c, 0, y, x.width, h); y += h; });
          var fd = new FormData(); fd.append('action', 'ir_tl_see'); fd.append('n', IRC.n); fd.append('img', x.toDataURL('image/jpeg', 0.85)); fd.append('sys', sys(true)); fd.append('user', user(''));
          return fetch(IRC.aj, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) { if (!j || !j.ok) { throw new Error((j && j.err) || 'busy'); } return parse(j.text); });
        })();
        return seen.catch(function (e0) {
          if (viaSrv) { throw e0; }
          /* 2. Otherwise describe the screenshots on the phone, then write. */
          var look = canvases.length && IRMEDIA.see ? canvases.slice(0, 2).reduce(function (p, c, i) {
            return p.then(function (acc) { return new Promise(function (res) { c.toBlob(res, 'image/jpeg', 0.88); }).then(function (b) { return IRMEDIA.see(b, function (pp, m) { step(m); }); }).then(function (v) { acc.push('What screenshot ' + (i + 1) + ' shows: ' + v.caption + (v.objects ? ' (things in it: ' + v.objects + ')' : '')); return acc; }).catch(function () { return acc; }); });
          }, Promise.resolve([])) : Promise.resolve([]);
          return look.then(function (desc) {
            step('Finding what to mention...');
            return IRAI.askJSON({ system: sys(false), user: user(desc.length ? desc.join('\n') + '\n\n' : ''), max: 4000, onStatus: step }, function (j) { return (j.issues || []).length; });
          });
        }).then(function (d) {
          var strip = function (t) { return clean(t).replace(/^(i noticed\s+)?(your\s+)/i, '').replace(/[.\s]+$/, ''); };
          d.issues = (d.issues || []).slice(0, 5).map(function (x) { return { title: x.title || '', detail: x.detail || '', obs: strip(x.obs || x.title || ''), impact: clean(x.impact || ''), fix: x.fix || '' }; });
          d.like = strip(d.like || '').replace(/^i really like\s+/i, '');
          d.likes = (Array.isArray(d.likes) ? d.likes : []).map(function (x) { return strip(String(x || '')).replace(/^i really like\s+/i, ''); }).filter(function (x) { return x && x !== d.like; }).slice(0, 3);
          d.tip = clean(d.tip || '');
          d.need = Math.max(1, Math.min(10, +d.need || 6));
          d.best = d.best || {}; if (!/^d-[a-z]+$/.test(d.best.dm || '')) { d.best.dm = 'd-problem'; } if (!/^e-[a-z]+$/.test(d.best.email || '')) { d.best.email = 'e-problem'; }
          var c0 = (f && f.contact) || {}, c1 = d.contact || {};
          var tEm = (src.match(/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i) || [])[0] || '', tPh = (src.match(/(?:\+?234[\s-]?\(?0?\)?|\b0)[789][01]\d[\s-]?\d{3}[\s-]?\d{4}\b/) || [])[0] || '', tWa = (src.match(/wa\.me\/\+?(\d{8,15})/i) || [])[1] || '';
          if (tPh) { tPh = '+234' + digits(tPh).replace(/^(234)?0?/, ''); }
          if (!c0.email && tEm) { c0.email = tEm; } if (!c0.phone && tPh) { c0.phone = tPh; } if (!c0.whatsapp && tWa) { c0.whatsapp = tWa; }
          d.contact = { email: c0.email || (/@.+\./.test(c1.email || '') ? c1.email : ''), whatsapp: digits(c0.whatsapp || c1.whatsapp || ''), instagram: igHandle(c0.instagram || c1.instagram || ''), phone: c0.phone || c1.phone || '' };
          if (d.ng == null && NGRE.test(src + ' ' + d.contact.phone + ' ' + d.contact.whatsapp)) { d.ng = true; }
          if (!d.business && good && f.name) { d.business = f.name; }
          return d;
        });
      });
    }).then(function (d) {
      busyAi = false; SCAN = { url: u, data: d, pick: -1 }; scanApply(0, true); draw();
      var e = $('#swScan'); if (e) { e.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
      flash('Done. We filled in the compliment, what they are missing and how it affects them.');
    }).catch(function (x) { busyAi = false; IRMEDIA.stopOcr(); SCAN = { url: u, err: x.message }; draw(); });
  }
  function bindScan() {
    var d = $('#swScan'); if (!d) { return; }
    d.addEventListener('toggle', function () { scanOpen = d.open; });
    $('#scGo').onclick = scanRun;
    $('#scUrl').onkeydown = function (e) { if (e.key === 'Enter') { e.preventDefault(); scanRun(); } };
    var fi = $('#scShot'); $('#scShotB').onclick = function () { fi.click(); };
    fi.onchange = function () { readPr(); SHOTS = [].slice.call(fi.files || []).filter(function (x) { return /^image\//.test(x.type); }).slice(0, 4); if (!SCAN) { SCAN = { url: clean($('#scUrl').value) }; } else { SCAN.url = clean($('#scUrl').value); } scanOpen = true; draw(); };
    $$('[data-iss]').forEach(function (b) { b.onclick = function () { scanApply(+b.getAttribute('data-iss')); draw(); var o = $('#pr-obs'); if (o) { o.scrollIntoView({ behavior: 'smooth', block: 'center' }); } }; });
    $$('[data-lk]').forEach(function (b) { b.onclick = function () { readPr(); var all = [SCAN.data.like].concat(SCAN.data.likes || []); PR.like = all[+b.getAttribute('data-lk')] || PR.like; draw(); flash('Compliment changed.'); }; });
    var ua = $('#scUseAp'); if (ua) { ua.onclick = function () { readPr(); ST.ap = SCAN.data.best[ST.ch]; draw(); flash('Approach changed to ' + curAp().n); }; }
  }
  function wizHtml() {
    var last = ST.step === lastStep();
    return '<section class="sw-card sw-wiz">' + progHtml() + '<div class="sw-step">' + stepBody() + '</div><div class="sw-err" id="swErr" hidden></div>' +
      '<div class="sw-nav">' + (ST.step > 1 ? '<button type="button" class="sw-btn ghost" id="swBack">Back</button>' : '<span></span>') + '<button type="button" class="sw-go" id="swNext"' + (busyAi ? ' disabled' : '') + '>' + (last ? (isBulk() ? '⚡ Write my messages' : isReply() ? '✨ Write my replies' : '✨ Write my ' + (ST.ch === 'email' ? 'email' : 'messages')) : 'Next') + '</button></div></section>';
  }
  function draw() {
    readMe(); var y = window.scrollY || 0;
    root.innerHTML = '<div class="sw">' + meHtml() + (ST.view === 'out' ? '' : wizHtml()) + '<div id="swOut"></div></div>';
    bindAll(); if (ST.view === 'out') { showOut(); } window.scrollTo(0, y);
  }
  var meT = null;
  function autoSaveMe() {
    readMe(); try { localStorage.setItem('irsw', JSON.stringify(ME)); } catch (e) { }
    clearTimeout(meT);
    if (meDone()) { meT = setTimeout(function () { var f = new FormData(); f.append('action', 'ir_rw_profile'); f.append('n', W.n || ''); PK.forEach(function (k) { f.append(k, ME[k]); }); fetch(W.aj || C.aj, { method: 'POST', body: f, credentials: 'same-origin' }).catch(function () { }); }, 1500); }
  }
  function readMe() { PK.forEach(function (k) { var e = $('#me-' + k); if (e) ME[k] = clean(e.value); }); }
  function readPr() {
    ['name', 'biz', 'obs', 'impact', 'like', 'tip', 'handle'].forEach(function (k) { var e = $('#pr-' + k); if (e) PR[k] = e.value; });
    var c = $('#pr-contact'); if (c) { PR.contact = c.value; }
    var bl = $('#bk-list'); if (bl) { PR.blist = bl.value; }
    var rp = $('#rp-reply'); if (rp) { PR.reply = rp.value; } var cx = $('#rp-ctx'); if (cx) { PR.ctx = cx.value; }
  }
  function liveNote(id, lead) {
    var i = $('#' + id), p = $('#' + id + '-pv'); if (!i || !p) { return; }
    var f = function () { var v = phrase(i.value); p.textContent = v ? lead + ' ' + v + '.' : p.getAttribute('data-h'); };
    p.setAttribute('data-h', p.textContent); i.oninput = f;
    if (i.value) { f(); }
  }
  function bindAll() {
    var d = $('#swMe'); if (d) d.addEventListener('toggle', function () { meOpen = d.open; });
    $$('#swMe input').forEach(function (i) { i.oninput = function () { var p = $('#swPitch'); if (p) { p.textContent = pitch(); } autoSaveMe(); }; });
    $('#swSaveMe').onclick = function () {
      readMe(); var m = $('#swMeMsg');
      if (!meDone()) { m.textContent = 'Fill your name, what you do, who you help and the result.'; m.className = 'sw-msg bad'; return; }
      try { localStorage.setItem('irsw', JSON.stringify(ME)); } catch (e) { }
      var f = new FormData(); f.append('action', 'ir_rw_profile'); f.append('n', W.n || ''); PK.forEach(function (k) { f.append(k, ME[k]); });
      fetch(W.aj || C.aj, { method: 'POST', body: f, credentials: 'same-origin' }).catch(function () { });
      m.textContent = 'Saved'; m.className = 'sw-msg'; meOpen = false; readPr(); draw(); flash('Details saved');
    };
    if (ST.view === 'out') { return; }
    liveNote('pr-obs', 'I noticed'); liveNote('pr-like', 'I really like');
    $$('[data-seg]').forEach(function (g) { $$('button', g).forEach(function (b) { b.onclick = function () {
      readPr(); var k = g.getAttribute('data-seg'), v = b.getAttribute('data-v');
      if (k === 'via') { PR.via = v; PR.contact = ''; } else { ST[k] = v; if (k === 'ch' || k === 'type') { pickDefault(); if (k === 'ch') { PR.contact = ''; } } LEAD = LEAD && ST.type !== 'first' ? LEAD : null; }
      persist(); draw();
    }; }); });
    $$('[data-ap]').forEach(function (b) { b.onclick = function () { readPr(); ST.ap = b.getAttribute('data-ap'); draw(); }; });
    $$('[data-bap]').forEach(function (b) { b.onclick = function () { readPr(); PR.bap = b.getAttribute('data-bap'); var m = { 'd-problem': 'problem', 'd-audit': 'compliment', 'd-soft': 'soft', 'd-direct': 'direct' }; PR.bang = m[PR.bap] || 'problem'; draw(); }; });
    var bkf = $('#bkFind'); if (bkf) { bkf.onclick = function () { findBulk(null); }; }
    $$('[data-gset]').forEach(function (b) { b.onclick = function () { readPr(); S.goal = +b.getAttribute('data-gset'); save(); draw(); flash('Daily goal set to ' + S.goal); }; });
    $$('[data-goal] button').forEach(function (b) { b.onclick = function () { readPr(); PR.goal = b.getAttribute('data-v'); draw(); }; });
    bindScan();
    $$('[data-go]').forEach(function (b) { b.onclick = function () { readPr(); ST.step = +b.getAttribute('data-go'); draw(); }; });
    var bk = $('#swBack'); if (bk) { bk.onclick = function () { readPr(); ST.step = Math.max(1, ST.step - 1); draw(); }; }
    $('#swNext').onclick = next;
  }
  function stepError() {
    readMe(); readPr();
    var a = curAp(), M = normPr(PR), P = normMe(ME);
    if (ST.step === 3 || ST.step === 4) {
      if (!meDone()) { return { msg: 'Add your details first (name, what you do, who you help, and the result).', open: 1 }; }
    }
    if (isBulk() && ST.step === 2) {
      if (!meDone()) { return { msg: 'Add your details first (name, what you do, who you help, and the result).', open: 1 }; }
      if (!bulkList().length && !bulkRaw().length) { return { msg: 'Paste at least one Instagram link or @handle, one per line.' }; }
      return null;
    }
    if (isReply() && ST.step === 2) {
      if (!clean(PR.reply)) { return { msg: 'Paste what they replied.' }; }
      return null;
    }
    if (isReply() && ST.step === 3) { ST.step = 4; var e4 = stepError(); ST.step = 3; return e4; }
    if (ST.step === 3) {
      var miss = [];
      needs(a).forEach(function (n) { if (n === 'obs' && !M.obs) { miss.push('what you noticed about them'); } if (n === 'like' && !M.like) { miss.push('something you like about their work'); } if (n === 'tip' && !M.tip) { miss.push('a quick tip for them'); } if (n === 'proof' && !P.proof) { miss.push('one real client result (open Your details, then More details)'); } });
      if (miss.length) { return { msg: 'Please add ' + miss.join(' and ') + '.', open: miss.join(' ').indexOf('client result') > -1 }; }
    }
    if (ST.step === 4) {
      var c = clean(PR.contact);
      if (c) {
        if (ST.ch === 'email' && !emailOk(c)) { return { msg: 'That email address does not look right. Check it for typos.' }; }
        if (ST.ch === 'dm' && PR.via === 'wa' && !waOk(c)) { return { msg: 'The WhatsApp number needs the country code and no zero at the start, for example 2348012345678.' }; }
        if (ST.ch === 'dm' && PR.via === 'ig' && !igHandle(c)) { return { msg: 'Add their Instagram handle or profile link.' }; }
      }
    }
    return null;
  }
  function next() {
    var bad = stepError(), er = $('#swErr');
    if (bad) { er.textContent = bad.msg; er.hidden = false; if (bad.open) { var d = $('#swMe'); if (d) { d.open = true; meOpen = true; d.scrollIntoView({ behavior: 'smooth', block: 'start' }); } } return; }
    if (ST.step < lastStep()) { ST.step += 1; draw(); return; }
    if (isReply()) { writeReplies(); return; }
    if (ST.type === 'fu' && LEAD && ((LEAD.log || []).length || LEAD.notes) && window.IRAI) { writeFollowAI(); return; }
    if (isBulk()) {
      if (bulkNeedsScan()) { findBulk(function () { next(); }); return; }
      if (!bulkList().length) { aiErr('Add their @handles, one per line.'); return; }
      if (!PR.bap) { PR.bap = bulkAp().k; }
      OUT = { bulk: genBulk(bulkList(), PR.bang), ap: { n: 'Bulk DMs · ' + bulkAp().n }, sent: {}, added: {} };
      OUT.bulk.forEach(function (x, i) { if (!S.leads.some(function (l) { return String(l.h).toLowerCase() === String(x.h).toLowerCase() && l.sent === today(); })) { addLead({ h: x.h, at: true, pl: 'Instagram', ch: 'dm', ang: x.ap || 'Bulk', notes: x.obs ? 'Noticed ' + x.obs : '', obs: x.obs, contact: { ig: x.h } }); } OUT.added[i] = 1; }); logged = false; LASTSENT = ''; ST.view = 'out'; draw(); return;
    }
    var a = curAp(), P = normMe(ME), M = normPr(PR), X = P0(P, M), r = complimentFirst(a.f(P, M, X), M, X);
    var show = function () { OUT = { ap: a, subject: r.subject || '', body: r.body || '', msgs: r.msgs || null, br: r.br || [] }; logged = false; LASTSENT = ''; prev = null; ST.view = 'out'; draw(); };
    if (!window.IRAI || !IRAI.post) { show(); return; }
    var sd = SCAN && SCAN.data ? SCAN.data : {}, b = $('#swNext');
    var facts = ['Business: ' + X.bz, PR.handle ? 'Instagram: ' + PR.handle : '', sd.summary ? 'Summary: ' + sd.summary : '', 'Compliment ideas: ' + [M.like].concat(sd.likes || []).filter(Boolean).join(' / '), M.obs ? 'What I noticed: ' + M.obs : '', M.impact ? 'What it costs them: ' + M.impact : '', sd.facts ? 'Their profile:\n' + sd.facts : ''].filter(Boolean).join('\n');
    var me = ['Name: ' + (P.me || ''), 'What I do: ' + (P.job || ''), 'Who I help: ' + (P.client || ''), 'Result: ' + (P.result || ''), 'How: ' + (P.service || ''), P.proof ? 'Proof: ' + P.proof : '', P.link ? 'Link: ' + P.link : ''].filter(Boolean).join('\n');
    var draft = r.msgs ? { msgs: r.msgs.map(function (m) { return m[1]; }) } : { subject: r.subject || '', body: r.body || '' };
    busyAi = true; if (b) { b.disabled = true; b.textContent = 'Making it personal...'; }
    var done = false, to = setTimeout(function () { if (!done) { done = true; busyAi = false; show(); } }, 45000);
    IRAI.post('ir_ct_polish', { ch: ST.ch, draft: JSON.stringify(draft), facts: facts.slice(0, 6000), me: me }).then(function (j) {
      if (done) { return; } done = true; clearTimeout(to); busyAi = false;
      if (j && j.ok) { if (r.msgs && j.msgs && j.msgs.length === r.msgs.length) { r.msgs = r.msgs.map(function (m, i) { return [m[0], j.msgs[i]]; }); } else if (!r.msgs && j.body) { r.subject = j.subject || r.subject; r.body = j.body; } }
      show();
    }).catch(function () { if (!done) { done = true; clearTimeout(to); busyAi = false; show(); } });
  }

  /* First messages always open with a sincere compliment when we have one. */
  function complimentFirst(r, M, X) {
    if (!M.like || ST.type !== 'first') { return r; }
    var c = 'I really like ' + M.like + '.';
    if (r.msgs && r.msgs.length) {
      var all = r.msgs.map(function (m) { return m[1]; }).join(' ');
      if (all.indexOf(M.like) < 0) { var m0 = r.msgs[0][1]; r.msgs[0][1] = m0.indexOf(X.hi) === 0 ? X.hi + ' ' + c + m0.slice(X.hi.length) : c + ' ' + m0; }
    } else if (r.body && r.body.indexOf(M.like) < 0) {
      var parts = r.body.split('\n\n'), at = parts[0] === X.hi ? 1 : 0;
      parts.splice(at, 0, c); r.body = parts.join('\n\n');
    }
    return r;
  }
  function lead2ctx(l) {
    if (!l) { return ''; }
    var s = 'First ' + (isEmail(l) ? 'email' : 'DM') + ' sent ' + l.sent + (l.ang ? ' using the ' + l.ang + ' approach' : '') + '.' + (l.biz ? ' Their business: ' + l.biz + '.' : '') + (l.obs ? ' What I noticed about them and pointed out: ' + l.obs + '.' : '') + (l.like ? ' What I complimented: ' + l.like + '.' : '') + (l.fu ? ' I followed up ' + l.fu + ' time' + (l.fu > 1 ? 's' : '') + '.' : '') + (l.notes ? '\nMy notes: ' + l.notes : '');
    var lg = (l.log || []).slice(-14);
    if (lg.length) { s += '\n\nTHE CONVERSATION SO FAR (oldest first):\n' + lg.map(function (x) { return (x.w === 'me' ? 'ME' : 'THEM') + (x.d ? ' (' + x.d + ')' : '') + ': ' + String(x.t).slice(0, 900); }).join('\n'); }
    return s;
  }
  /* Every message sent and every reply, kept on the lead so the AI can carry the conversation on. */
  function addLog(l, w, t) { t = String(t || '').trim(); if (!l || !t) { return; } l.log = l.log || []; var z = l.log[l.log.length - 1]; if (z && z.w === w && z.t === t) { return; } l.log.push({ w: w, t: t.slice(0, 1200), d: today() }); if (l.log.length > 14) { l.log = l.log.slice(-14); } }
  function allSent() { if (!OUT) { return LASTSENT; } if (OUT.msgs && OUT.msgs.length && !OUT.reply) { return OUT.msgs.map(function (m) { return m[1]; }).join('\n\n'); } return LASTSENT || ((OUT.subject ? 'Subject: ' + OUT.subject + '\n' : '') + (OUT.body || '')); }
  function sentText() { return LASTSENT || allSent(); }
  function stName(l) { var x = STAGE[l.st]; return x ? x[1] : (l.st || ''); }
  function leadPick() {
    var ls = live().filter(function (l) { return l.st !== 'no'; }).slice(0, 120);
    if (!ls.length) { return ''; }
    return '<label class="sw-f"><span>Who replied?</span><select id="swLeadPick"><option value="">Someone new (not in my tracker)</option>' + ls.map(function (l) { return '<option value="' + esc(l.id) + '">' + esc(who(l) + (l.biz && l.biz !== who(l) ? ' · ' + l.biz : '') + ' · ' + stName(l)) + '</option>'; }).join('') + '</select><small>Pick them and the AI remembers what you sent, what you noticed about them and everything they said.</small></label><div class="sw-gap"></div>';
  }
  function leadHist(l) {
    var lg = (l.log || []).slice(-6), h = '<p class="sw-lead">Conversation with <b>' + esc(who(l)) + '</b>' + (l.ang ? ' · you opened with ' + esc(l.ang) : '') + ' · <button type="button" class="sw-link" id="swUnpick">change</button></p>';
    if (l.obs || l.like) { h += '<p class="sw-hint" style="margin:6px 0 0">' + (l.obs ? '🔎 You noticed: ' + esc(l.obs) + '. ' : '') + (l.like ? '💬 You praised: ' + esc(l.like) + '.' : '') + '</p>'; }
    if (lg.length) { h += '<div class="sw-thread">' + lg.map(function (x) { return '<div class="sw-tb ' + (x.w === 'me' ? 'me' : 'them') + '">' + esc(String(x.t).slice(0, 400)) + '</div>'; }).join('') + '</div>'; }
    else if (l.notes) { h += '<p class="sw-hint" style="margin:6px 0 0;white-space:pre-line">' + esc(l.notes) + '</p>'; }
    return h + '<div class="sw-gap"></div>';
  }
  function pickLead(id) {
    var r = document.getElementById('rp-reply'); if (r) { PR.reply = r.value; }
    var l = (S.leads || []).filter(function (x) { return x.id === id; })[0] || null;
    LEAD = l;
    if (l) {
      ST.ch = isEmail(l) ? 'email' : 'dm'; PR.name = l.name || ''; PR.biz = l.biz || ''; PR.obs = l.obs || ''; PR.like = l.like || '';
      var ct = l.contact || {};
      if (ST.ch === 'email') { PR.contact = ct.email || (/@/.test(l.h) ? l.h : ''); }
      else if (ct.wa) { PR.via = 'wa'; PR.contact = ct.wa; }
      else if (ct.ig) { PR.via = 'ig'; PR.contact = '@' + ct.ig; }
      else if (l.at) { PR.via = 'ig'; PR.contact = '@' + l.h; }
    }
    draw();
  }
  document.addEventListener('change', function (ev) { if (ev.target && ev.target.id === 'swLeadPick') { pickLead(ev.target.value); } });
  document.addEventListener('click', function (ev) { if (ev.target && ev.target.id === 'swUnpick') { var r = document.getElementById('rp-reply'); if (r) { PR.reply = r.value; } LEAD = null; draw(); } });
  function writeFollowAI() {
    readMe();
    var a = curAp(), P = normMe(ME), em = ST.ch === 'email', b = $('#swNext'), n = (LEAD.fu || 0) + 1;
    busyAi = true; b.disabled = true; b.textContent = 'Reading your conversation...';
    getScripts(ST.ch).then(function (scr) {
      var sys = 'You write follow-up ' + (em ? 'emails' : 'DMs') + ' for a freelancer doing cold outreach. You get the whole history with this prospect: what the freelancer noticed about their business, every message sent and anything they replied. Write follow-up number ' + n + ' (' + a.n + ').\n\n' +
        'Rules:\n- It must clearly continue THIS conversation: refer to where it stopped, never repeat an earlier message, and bring one new thing (a fresh detail from what I noticed about them, a quick tip, a short example or a simple question).\n- If they replied before, pick up from their last reply instead of starting over.\n- Short and easy to answer, with one clear question or next step. Never write just checking in, bumping this or any guilt trip.\n- Never invent prices, results, client names or guarantees, and never use placeholders like [LINK].\n- ' + (em ? 'Email: a short subject line and 40 to 100 words, signed with my first name.' : 'DM: 1 or 2 short messages, like texting. No sign-off.') + '\n- Plain warm English, contractions, no em dashes or en dashes. Match the language of the conversation.\n\n' +
        (scr ? 'Ideas from the System (use their spirit, never copy {placeholders}):\n' + String(scr).slice(0, 6000) + '\n\n' : '') +
        'Reply with JSON only: {' + (em ? '"subject":"","body":""' : '"msgs":["message 1"]') + ',"why":"one sentence on why this follow-up works"}';
      var user = 'THE HISTORY WITH THIS PROSPECT\n' + lead2ctx(LEAD) + '\n\n' + (LEAD.name ? 'Prospect name: ' + LEAD.name + '\n' : '') + 'Freelancer first name: ' + P.me + '\n' + meLines() + '\nToday: ' + today();
      return IRAI.askJSON({ system: sys, user: user, max: 2000, onStatus: function (t) { var x = $('#swNext'); if (x) { x.textContent = t; } } }, function (j) { return em ? !!j.body : (j.msgs || []).length; });
    }).then(function (d) {
      busyAi = false;
      var msgs = em ? null : (d.msgs || []).slice(0, 2).map(function (t, i) { return [i ? 'Then send' : a.n, String(t).trim()]; });
      OUT = { ap: Object.assign({}, a, { note: d.why || '' }), subject: em ? String(d.subject || '') : '', body: em ? String(d.body || '') : '', msgs: msgs, br: [] }; logged = false; LASTSENT = ''; prev = null; ST.view = 'out'; draw();
    }).catch(function (x) { busyAi = false; var nb = $('#swNext'); if (nb) { nb.disabled = false; nb.textContent = 'Try again'; } aiErr(x.message); });
  }
  function writeReplies() {
    if (!window.IRAI) { aiErr('Please refresh the page and try again.'); return; }
    readMe();
    var M = normPr(PR), P = normMe(ME), ch = ST.ch, b = $('#swNext');
    busyAi = true; b.disabled = true; b.textContent = 'Reading their reply...';
    getScripts(ch).then(function (scr) {
      var sys = 'You are a calm, experienced closer helping a freelancer reply to a prospect in a cold ' + (ch === 'email' ? 'email thread' : 'DM conversation') + '. Understand what the prospect really means, then write replies that move the conversation forward without sounding salesy or desperate.\n\n' +
        'How to reply:\n- Acknowledge what they said first, in their own words where possible.\n- Answer any question directly and honestly.\n- If it is an objection (price, timing, already have someone, think about it, busy), use the matching approach from the scripts below, adapted to this conversation. Never argue.\n' +
        '- End with one easy next step: a short question, a call time, a free sample, or permission to follow up later.\n- If they are clearly not interested, leave the door open politely in one or two lines.\n' +
        '- Read the whole conversation first. Their latest reply often points back to my last message (for example: yes, sure, ok, how?, what do you mean, send it): understand it against what I said last and the problem I noticed about them, and answer exactly that. If they ask a question, answer it in the first sentence.\n' +
        '- Keep selling my offer gently: link what they said to the result I get for clients like them, then one clear next step.\n' +
        '- Never invent prices, results, client names or guarantees, and never use placeholders like [PRICE] or [LINK]. If my price or link is not in my details, say it plainly (for example: it depends on what you need, I can give you the exact number on a quick call).\n' +
        '- ' + (ch === 'email' ? 'Emails: 40 to 120 words, sign off with the freelancer\'s first name.' : 'DMs: short, like texting, 1 to 4 short sentences. No sign-off.') + '\n- Plain, warm English. Contractions. No em dashes or en dashes. Match their language and tone.\n\n' +
        (scr ? 'The System\'s own scripts (use their ideas, never copy {placeholders}):\n' + scr + '\n\n' : '') +
        'Reply with JSON only: {"kind":"interested, question, price, objection, not_now, not_interested, referral or other","label":"2 to 5 word name for this reply, e.g. Asked about price","meaning":"what they really mean, 1 or 2 sentences","stage":"replied, interested, call, client or no","replies":[{"label":"short name of this route, e.g. Book the call","text":"the message to send"}],"next":"what to do after sending, 1 sentence","avoid":"one thing not to say, 1 sentence"}. Give exactly 3 replies, each a different route.';
      var user = (LEAD ? 'What happened so far: ' + lead2ctx(LEAD) + '\n' : clean(PR.ctx) ? 'What happened so far: ' + clean(PR.ctx) + '\n' : '') + ((LEAD ? LEAD.name : M.name) ? 'Prospect name: ' + (LEAD ? LEAD.name : M.name) + '\n' : '') + 'Freelancer first name: ' + P.me + '\n' + meLines() + '\nWhat I want next: ' + PR.goal + '\n\nTheir latest reply:\n<<<\n' + String(PR.reply).slice(0, 3000) + '\n>>>';
      return IRAI.askJSON({ system: sys, user: user, max: 3500, onStatus: function (t) { var x = $('#swNext'); if (x) { x.textContent = t; } } }, function (j) { return (j.replies || []).length; });
    }).then(function (d) {
      d.replies = (d.replies || []).slice(0, 3).map(function (r) { return { label: r.label || 'Reply', text: String(r.text || r).trim() }; });
      if (['replied', 'interested', 'call', 'client', 'no'].indexOf(d.stage) < 0) { d.stage = 'replied'; }
      busyAi = false; OUT = { reply: d, ap: { n: d.label || 'Reply', note: '' }, msgs: d.replies.map(function (r) { return [r.label, r.text]; }), br: [] }; logged = false; LASTSENT = ''; ST.view = 'out'; draw();
    }).catch(function (x) { busyAi = false; var nb = $('#swNext'); if (nb) { nb.disabled = false; nb.textContent = '✨ Write my replies'; } aiErr(x.message); });
  }
  function replyOut() {
    var d = OUT.reply, st = STAGE[d.stage] || STAGES[2], v = via();
    var h = '<div class="sw-out-h"><h3>✓ 3 replies ready</h3><button type="button" class="sw-link" id="swEdit">Edit answers</button></div>' +
      '<div class="sw-rk"><span class="sw-rk-t">' + esc(d.label || 'Their reply') + '</span><p>' + esc(d.meaning) + '</p></div>' +
      (v ? '' : '<p class="sw-hint">' + (ST.ch === 'email' ? 'Add their email address' : 'Add their WhatsApp or Instagram') + ' (tap Edit answers) to send in one tap. You can still copy each reply.</p>') +
      '<div class="sw-msgs">' + OUT.msgs.map(function (m, i) { return msgCard(['Option ' + (i + 1) + ' · ' + m[0], m[1]], i, 'm'); }).join('') + '</div>' +
      (d.next ? '<p class="sw-note"><b>Next:</b> ' + esc(d.next) + '</p>' : '') + (d.avoid ? '<p class="sw-note sw-avoid"><b>Avoid:</b> ' + esc(d.avoid) + '</p>' : '') +
      '<p class="sw-hint" style="margin:10px 0 0">✓ ' + (LEAD ? esc(who(LEAD)) + ' is moved to <b>' + esc(st[1]) + '</b> in your tracker automatically.' : 'Added to your tracker as <b>' + esc(st[1]) + '</b> automatically.') + '</p>' +
      '<button type="button" class="sw-link" id="swAgain">Write for someone new</button>';
    return h;
  }

  /* ---------- bulk DMs: up to 5 people at once, each worded differently ---------- */
  var BANG = [['problem', 'Problem I noticed'], ['compliment', 'Compliment + offer'], ['soft', 'Soft question'], ['direct', 'Direct offer']];
  function bulkList() {
    return String(PR.blist || '').split('\n').map(function (l) {
      l = l.trim(); if (!l || !/^(@|https?:\/\/|(www\.)?instagram\.com\/)/i.test(l) && /\s/.test(l)) { return null; }
      var m = l.match(/^(\S+)\s*(?:[-–—:|,.]+\s*)?(.*)$/); if (!m) { return null; }
      var h = igHandle(m[1]); if (!/^[A-Za-z0-9._]{2,30}$/.test(h)) { return null; }
      return { h: h, obs: clean(m[2] || '') };
    }).filter(Boolean).slice(0, 10);
  }
  /* ---------- paste up to 5 pages: the site reads each one and finds what to mention ---------- */
  var BKF = '', BKDONE = null;
  function bulkRaw() { return String(PR.blist || '').split('\n').map(function (l) { return l.trim(); }).filter(Boolean).slice(0, 5); }
  function bulkSplit(l) { return l.match(/^(\S+)\s*(?:[-–—:|,.]+\s*)?(.*)$/); }
  function bulkNeedsScan() {
    if (PR.blist === BKDONE) { return false; }
    return bulkRaw().some(function (l) { var m = bulkSplit(l); return !!m && (!clean(m[2] || '') || !/^[A-Za-z0-9._]{2,30}$/.test(igHandle(m[1]))); });
  }
  function bulkPoll(job, say) {
    var tries = 0;
    return new Promise(function (res, rej) {
      (function poll() {
        if (++tries > 100) { rej(new Error('That took too long.')); return; }
        setTimeout(function () {
          IRAI.post('ir_tool_job', { job: job }).then(function (j) {
            if (j && j.wait) { say(j.step || 'Working...'); poll(); return; }
            if (j && j.ok) { res(j.data || {}); } else { rej(new Error((j && j.err) || 'Something went wrong.')); }
          }).catch(function () { poll(); });
        }, 2500);
      })();
    });
  }
  function findBulk(cb) {
    readPr();
    var raw = bulkRaw(), msg = $('#bkFindMsg'), bt = $('#bkFind'), go = $('#swNext'), er = $('#swErr'), t0 = Date.now();
    if (!raw.length) { aiErr('Paste up to 5 Instagram links or @handles first, one per line.'); return; }
    if (!meDone()) { aiErr('Add your details first (name, what you do, who you help, and the result).'); var dm = $('#swMe'); if (dm) { dm.open = true; meOpen = true; dm.scrollIntoView({ behavior: 'smooth', block: 'start' }); } return; }
    if (er) { er.hidden = true; }
    var say = function (t) { if (msg) { msg.className = 'sw-msg'; msg.textContent = t + ' ' + Math.round((Date.now() - t0) / 1000) + 's'; } };
    var end = function () { busyAi = false; if (bt) { bt.disabled = false; } if (go) { go.disabled = false; } };
    busyAi = true; if (bt) { bt.disabled = true; } if (go) { go.disabled = true; }
    say('Reading their pages...');
    var P = normMe(ME), keep = {};
    raw.forEach(function (l) { var m = bulkSplit(l); if (m && clean(m[2] || '')) { keep[m[1]] = clean(m[2]); } });
    IRAI.post('ir_tool_run', { tool: 'ctbulk', ang: PR.bang || 'problem', ap: bulkAp().n + ' (' + bulkAp().d + ')', list: raw.join('\n'), me_job: P.job, me_client: P.client, me_result: P.result, me_service: P.service || '' })
      .then(function (r) { if (!r || !r.ok) { throw new Error((r && r.err) || 'The page reader is busy.'); } return bulkPoll(r.job, say); })
      .then(function (d) { if (!d || !d._device) { return d; } say('Finding the problems on each page...'); return IRAI.ask({ system: d.sys, user: d.user, max: d.max, onStatus: function (m) { say(m); } }).catch(function () { return ''; }).then(function (t) { return IRAI.post('ir_v2_finish', { k: d.k, raw: t }); }).then(function (j) { if (!j || !j.ok) { throw new Error('The page reader is busy.'); } return j.data || {}; }); })
      .then(function (d) {
        var lines = [], rows = [], ok = 0, ppl = d.people || [];
        ppl.forEach(function (p) {
          if (p.h && p.dm && p.dm.length) { BKSEQ[String(p.h).toLowerCase()] = p.dm; }
          if (p.h && p.ng != null) { BKNG[String(p.h).toLowerCase()] = !!p.ng; }
          var note = keep[p.in] || p.note || '';
          if (p.h) { ok++; lines.push('@' + p.h + (note ? ' - ' + note : '')); } else { lines.push(p.in + (keep[p.in] ? ' - ' + keep[p.in] : '')); }
          rows.push('<div class="sw-m"><div class="sw-m-h"><b>' + esc(p.h ? '@' + p.h : p.in) + '</b>' + (p.business && String(p.business).toLowerCase() !== String(p.h).toLowerCase() ? '<small>' + esc(p.business) + '</small>' : '') + '</div>' +
            (p.summary ? '<p class="sw-hint" style="margin:6px 0 0">' + esc(p.summary) + '</p>' : '') +
            (note ? '<p style="margin:6px 0 0;font-size:14.5px"><b>To mention:</b> ' + esc(note) + '</p>' : (p.h && p.ok ? '<p class="sw-hint" style="margin:6px 0 0">Nothing obvious to fix, so the message opens with a friendly compliment.</p>' : '')) +
            (p.err ? '<p class="sw-msg bad" style="margin:6px 0 0">' + esc(p.err) + '</p>' : '') + '</div>');
        });
        raw.slice(ppl.length).forEach(function (l) { lines.push(l); });
        PR.blist = lines.join('\n'); BKDONE = PR.blist; BKF = '<div class="sw-msgs" style="margin-top:10px">' + rows.join('') + '</div>';
        end();
        var bl = $('#bk-list'); if (bl) { bl.value = PR.blist; }
        var fd = $('#bkFound'); if (fd) { fd.innerHTML = BKF; }
        if (msg) { msg.className = ok ? 'sw-msg' : 'sw-msg bad'; msg.textContent = ok ? (cb ? 'Found them. Writing your messages...' : 'Done. Check what we found, change anything you like, then tap Write my messages.') : 'We could not read these pages. Check the links, or add their @handles and what you noticed.'; }
        if (cb && ok) { cb(); }
      })
      .catch(function (e) { end(); BKDONE = PR.blist; if (msg) { msg.className = 'sw-msg bad'; msg.textContent = e.message + ' Try again, or tap Write my messages to write them without page notes.'; } });
  }
  function sentToday() { return total(day()); }
  function goalHtml() {
    var n = sentToday(), g = S.goal || 15;
    return '<div class="sw-goal"><div><b>' + n + '</b> of ' + g + ' sent today</div><div class="sw-goalbar"><i style="width:' + Math.min(100, Math.round(n / g * 100)) + '%"></i></div>' +
      '<div class="sw-gq">Daily goal: ' + [25, 50, 100].map(function (x) { return '<button type="button" data-gset="' + x + '" class="' + (g === x ? 'on' : '') + '">' + x + '</button>'; }).join('') + '</div></div>';
  }
  function bulkBody() {
    return '<h3>Who are you messaging?</h3>' + goalHtml() +
      '<div class="sw-lab">Your approach</div><div class="sw-chips">' + APPROACHES.dm.first.map(function (a) { return '<button type="button" data-bap="' + a.k + '" class="' + (bulkAp().k === a.k ? 'on' : '') + '" title="' + esc(a.d) + '">' + esc(a.n) + '</button>'; }).join('') + '</div><p class="sw-help" style="margin:6px 0 0">' + esc(bulkAp().d) + ' Each person gets the full message sequence for this approach.</p><div class="sw-gap"></div>' +
      '<label class="sw-f"><span>Up to 5 people, one per line <em>required</em></span><textarea id="bk-list" rows="6" placeholder="https://www.instagram.com/glowbyamaka&#10;@skinbytola&#10;@lagosbrows - last post was 3 weeks ago">' + esc(PR.blist) + '</textarea>' +
      '<small>Paste their Instagram profile links or @handles (websites work too), one per line. Tap <b>Find their problems</b> and we read each page and add what to mention. You can also type what you noticed after each one.</small></label>' +
      '<div class="sw-row"><button type="button" class="sw-btn" id="bkFind"' + (busyAi ? ' disabled' : '') + '>🔍 Find their problems for me</button></div><p class="sw-msg" id="bkFindMsg"></p><div id="bkFound">' + (PR.blist ? BKF : '') + '</div>';
  }
  var BK = {
    greet: ['Hey @{h},', 'Hi @{h}!', 'Hey @{h} 👋', 'Hi @{h}, hope your week is going well.', 'Hey @{h}, quick one.'],
    seen: ['I noticed {o}.', 'Quick thing I spotted: {o}.', 'I was going through your page and noticed {o}.', 'Something stood out on your page: {o}.', 'I came across your page and noticed {o}.'],
    like: ['I really like {o}.', 'Love {o}, it really stands out.', '{O} caught my eye straight away.', 'Big fan of {o}.', 'Honestly, {o} is so good.'],
    none: ['I came across your page and really like your work.', 'Your page caught my eye, the work is really clean.', 'Been seeing your posts and really like your style.', 'Just came across your page and had to reach out.', 'Love what you are building on your page.'],
    cost: ['Small things like that can quietly cost you bookings.', 'That can stop new people from reaching out.', 'That usually means interested people leave without booking.', 'It can make it harder for new clients to take the next step.', 'It is usually a quick fix with a big difference.'],
    who: ['I help {client} {result}.', 'I work with {client} to {result}.', "I'm {joba} who helps {client} {result}.", 'Helping {client} {result} is what I do.', 'I help {client} {result}, so it jumped out at me.'],
    ask: {
      problem: ['Want me to send 2 quick ideas to fix it?', 'Would it help if I shared a quick fix?', "Open to a 60 second video showing how I'd fix it?", 'Can I send you a couple of quick tips on that?', 'Mind if I share what I would change first?'],
      compliment: ['Would a free 3 point audit of your page be useful?', 'Can I send you 2 quick ideas to get even more from it?', 'Open to a quick tip that could bring in more clients?', 'Want me to share one thing I would add?', 'Would a short video with a few ideas help?'],
      soft: ['Quick question: are you happy with how many new clients your page brings in right now?', 'Curious, is getting more clients from Instagram something you are working on this month?', 'Quick question, do most of your clients find you on Instagram?', 'Are you taking on new clients at the moment?', 'Is growing your bookings a focus for you right now?'],
      direct: ['Open to a quick chat about it this week?', 'Would you be open to a 10 minute call this week?', 'Can I show you how I would do it for you?', 'Worth a quick chat?', 'Want me to send over how it would work for you?']
    }
  };
  function fill(t, v) { return t.replace(/\{(\w+)\}/g, function (m, k) { return v[k] != null ? v[k] : m; }); }
  /* Turn a quick note like "no reels or carousels love the reviews" into what they are missing and what you like. */
  var TYPO = [[/\beduca?ti?na?l\b/g, 'educational'], [/\btalkin[gh]?\b/g, 'talking'], [/\btalking head real(s?)\b/g, 'talking head reel$1'], [/\btalking (reel|video)/g, 'talking head $1'], [/\bhead head\b/g, 'head'], [/\bin (?:the )?bio\b/g, 'in your bio'], [/\bcarous[ae]ls?\b/g, 'carousels'], [/\bbts\b/g, 'behind-the-scenes'], [/\bai\b/g, 'AI'], [/\binstagram\b/g, 'Instagram'], [/\bfeeds?\b/g, 'feed'], [/\bclients results\b/g, "clients' results"]];
  function plural(w) { return w.replace(/\b(reel|flyer|carousel|video|post|story|review|photo|testimonial)\b(?!s)/g, function (x) { return x === 'story' ? 'stories' : x + 's'; }); }
  function parseNote(raw) {
    var t = ' ' + String(raw || '').toLowerCase().replace(/[.!]+/g, ',').replace(/\s+/g, ' ') + ' ';
    TYPO.forEach(function (r) { t = t.replace(r[0], r[1]); });
    var like = '', lm = t.match(/(?:,|\s)\s*(?:and\s+)?(?:i\s+)?(?:really\s+)?(?:love|loved|like|liked|adore)\s+(.+)$/);
    if (lm) { like = lm[1]; t = t.slice(0, lm.index); var cut = like.search(/(?:,|\s)\s*(?:but\s+|and\s+)?(?:no|not|lots of|a lot of|too many|zero|without)\s/); if (cut > -1) { t += ' ' + like.slice(cut); like = like.slice(0, cut); } like = like.replace(/[,\s]+$/, '').replace(/\s+(?:and|but)$/, '').trim(); }
    if (like) like = like.replace(/^the\s+/, 'your ').replace(/^(?!your\b|how\b|that\b)/, 'your ').replace(/\band the\b/g, 'and your');
    var gaps = [], other = [];
    t.split(/,|\band\b(?=\s+(?:no|not|lots|too|only)\b)|\s(?=(?:no|not|lots of|too many|only)\s)/).forEach(function (c) {
      c = c.trim(); if (!c) { return; }
      var g = c.match(/^(?:there (?:are|is) )?(?:no|not any|not|zero|without)\s+(.+)$/);
      if (g) { var parts = g[1].split(/\s+or\s+/); parts.forEach(function (x) { x = x.trim(); x = (parts.length > 1 || /\b(reel|carousel|flyer|video|post|story|review|testimonial)$/.test(x)) ? plural(x) : x; if (x) { gaps.push(x); } }); return; }
      var lot = c.match(/^(?:lots of|a lot of|too many|mostly|only)\s+(.+)$/);
      if (lot) { other.push('a lot of your posts are ' + lot[1]); return; }
      other.push(phrase(c));
    });
    var seen = {}; gaps = gaps.filter(function (x) { if (seen[x]) { return false; } seen[x] = 1; return true; });
    return { like: like, gaps: gaps, other: other, content: /reel|video|carousel|post|content|flyer|stor|photo/.test(gaps.join(' ')) };
  }
  function listOr(a) { return a.length < 2 ? (a[0] || '') : a.slice(0, -1).join(', ') + ' or ' + a[a.length - 1]; }
  var BN = {
    gap1: ['I noticed there is no {g}.', 'One thing I spotted: there is no {g}.', "I couldn't find a {g}.", 'Quick thing I noticed, there is no {g} yet.', "I noticed you don't have a {g} yet."],
    gap: ['I noticed there are no {g} on your page yet.', 'One thing I spotted: there are no {g} on your page.', "I couldn't find any {g} on your page.", 'Quick thing I noticed, there are no {g} yet.', "I noticed you don't have any {g} up yet."],
    other: ['I noticed {o}.', 'One thing I spotted: {o}.', 'I was going through your page and noticed {o}.', 'Quick thing I noticed, {o}.', 'I came across your page and noticed {o}.'],
    like: ['I love {l}.', 'Really love {l}.', '{L} caught my eye straight away.', 'Big fan of {l}.', 'Love {l}, so well done.'],
    costC: ['Those are usually what build trust and turn followers into bookings.', 'That kind of content is what gets new people to trust you enough to book.', 'Those posts are often what bring in new clients.', 'Adding them could bring you a lot more bookings from the same followers.', 'That is usually the easiest way to get more people booking.']
  };
  /* Each person gets a 3 message sequence: the AI's own when their page was read, a varied template otherwise. */
  var BKSEQ = {};
  var FU = [["Hey @{h}, no rush on my last message. One quick thing you could try this week: add a clear 'DM to book' line at the end of your next post. Want a couple more ideas like that?", "Hi @{h}, just floating this back up. I put together 2 small ideas for your page. Should I send them here?", "Hey @{h}, quick follow up. I noticed one easy win on your page that takes about 10 minutes. Want me to share it?", "@{h} hi again! I know DMs pile up. I still have those ideas for your page if you'd like them.", "Hey @{h}, me again. Happy to record a 60 second video walking through what I'd change first. Want it?"],
    ["Hi @{h}, last one from me. If now isn't the time, no problem at all. The offer stays open whenever you're ready.", "Hey @{h}, I'll leave it here so I don't crowd your inbox. If you ever want those ideas, just reply 'yes'.", "@{h} closing the loop on this. Rooting for your page either way. Message me anytime.", "Hi @{h}, I won't keep nudging. If getting more bookings from Instagram moves up your list, I'm one message away.", "Hey @{h}, final check in. All good if it's a no. Keep up the great work."]];
  var BKNG = {};
  function fuT(h, i, k) { return ngFix(FU[k][Math.abs(i) % FU[k].length].replace('{h}', h), { handle: h, ng: BKNG[String(h).toLowerCase()] }); }
  document.addEventListener('click', function (e) { var b = e.target.closest && e.target.closest('[data-fuc]'); if (!b) { return; } var t = document.getElementById(b.getAttribute('data-fuc')); if (!t) { return; } try { navigator.clipboard.writeText(t.value); } catch (x) { t.select(); try { document.execCommand('copy'); } catch (y) { } } b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy'; }, 1400); });
  function bulkAp() { var a = null; APPROACHES.dm.first.forEach(function (x) { if (x.k === PR.bap) { a = x; } }); return a || APPROACHES.dm.first[0]; }
  /* Bulk DMs follow the same message sequence as the approach picked, one sequence per person, filled from what we found on each page. */
  function genBulkAp(list) {
    var P = normMe(ME), a0 = bulkAp(), seed = Math.floor(Math.random() * 5);
    return list.map(function (x, i) {
      var N = parseNote(x.obs), obs = N.other[0] || (N.gaps.length ? 'page has no ' + listOr(N.gaps) : '');
      obs = String(obs).replace(/^(i noticed\s+)?(your\s+)/i, '').replace(/[.\s]+$/, '');
      /* The approach the member picked is used for every person. When nothing was found to point at, a fill-in marks the gap instead of switching approach. */
      var miss = needs(a0).indexOf('obs') > -1 && !obs;
      var M = { name: '', biz: '@' + x.h, obs: miss ? '{what you noticed on their page}' : obs, impact: '', like: N.like || '', tip: '', handle: x.h, ng: BKNG[String(x.h).toLowerCase()] };
      var a = a0;
      var X = P0(P, M), r = complimentFirst(a.f(P, M, X), M, X), msgs = r.msgs && r.msgs.length ? r.msgs : [['Message', (r.body || '')]];
      msgs = msgs.map(function (m) { return [m[0], String(m[1] || '').replace(/\s+([.,!?])/g, '$1').replace(/ {2,}/g, ' ').trim()]; }).filter(function (m) { return m[1]; });
      return { h: x.h, obs: x.obs, miss: miss, ap: a.n, msgs: msgs, text: msgs[0][1], fu: [fuT(x.h, i + seed, 0), fuT(x.h, i + seed + 2, 1)] };
    });
  }
  function genBulk(list, ang) {
    return genBulkAp(list);
    var P = normMe(ME), seed = Math.floor(Math.random() * 5), base = { client: P.client, result: P.result, joba: article(P.job) };
    return list.map(function (x, i) {
      var k = function (arr, off) { return arr[(i + seed + off) % arr.length]; };
      var sq = BKSEQ[String(x.h).toLowerCase()];
      if (sq && sq[0]) { var t0 = String(sq[0]); if (t0.toLowerCase().indexOf('@' + String(x.h).toLowerCase()) < 0) { t0 = 'Hey @' + x.h + ', ' + t0.charAt(0).toLowerCase() + t0.slice(1); } return { h: x.h, obs: x.obs, text: t0, fu: [sq[1] || fuT(x.h, i + seed, 0), sq[2] || fuT(x.h, i + seed, 1)] }; }
      var N = parseNote(x.obs), hasGap = N.gaps.length || N.other.length, v = Object.assign({ h: x.h, g: listOr(N.gaps), o: N.other.join(' and '), l: N.like, L: cap1(N.like) }, base);
      var hi = fill(k(BK.greet, 0), v), lk = N.like ? fill(k(BN.like, 1), v) : '', lines = [];
      var one = N.gaps.length === 1 && !/s$/.test(N.gaps[0]), gs = (N.gaps.length ? fill(k(one ? BN.gap1 : BN.gap, 2), v) : '') + (N.other.length ? (N.gaps.length ? ' ' : '') + (N.gaps.length ? fill('I also noticed {o}.', v) : fill(k(BN.other, 0), v)) : '');
      var cost = N.content ? k(BN.costC, 4) : k(BK.cost, 4), who = fill(hasGap ? k(BK.who, 2) : BK.who[(i + seed) % 4], v);
      if (ang === 'soft') { lines = [hi + ' ' + (lk || k(BK.none, 1)) + (gs ? ' ' + gs : ''), k(BK.ask.soft, 3)]; }
      else if (ang === 'compliment') { lines = [hi + ' ' + (lk || k(BK.none, 1)), BK.who[(i + seed) % 4].replace(/\{(\w+)\}/g, function (m, q) { return v[q]; }) + ' ' + k(BK.ask.compliment, 3)]; }
      else if (ang === 'direct') { lines = [hi + ' ' + (lk ? lk + ' ' : '') + who + (gs ? ' ' + gs : ''), k(BK.ask.direct, 3)]; }
      else { lines = hasGap ? [hi + ' ' + (lk ? lk + ' ' : '') + gs + ' ' + cost, who, k(BK.ask.problem, 3)] : [hi + ' ' + (lk || k(BK.none, 1)), who, k(BK.ask.compliment, 3)]; }
      lines[0] = lines[0].replace(/^([^\n]*?@[\w.]+,) (?!I\b|I'|Instagram)([A-Z])/, function (m, g, c) { return g + ' ' + c.toLowerCase(); });
      return { h: x.h, obs: x.obs, fu: [fuT(x.h, i + seed, 0), fuT(x.h, i + seed + 2, 1)], text: lines.join('\n\n').replace(/\s+([.,!?])/g, '$1').replace(/ {2,}/g, ' ') };
    });
  }
  function bulkOut() {
    var L = OUT.bulk, n = L.length, done = Object.keys(OUT.sent).length;
    return '<div class="sw-out-h"><h3>✓ ' + n + ' message' + (n > 1 ? 's' : '') + ' ready</h3><button type="button" class="sw-link" id="swEdit">Edit list</button></div>' + goalHtml() +
      '<p class="sw-hint">' + (L[0] && L[0].msgs && L[0].msgs.length > 1 ? 'Each person gets the full <b>' + esc(OUT.ap.n.replace('Bulk DMs · ', '')) + '</b> sequence. Tap <b>Open DM</b>: message 1 is copied and their chat opens. Send it, then copy and send the next messages separately.' : 'Each one is worded differently and comes with 2 follow-ups. Tap <b>Open DM</b>: the message is copied, their chat opens in Instagram, paste and send.') + ' It is logged in your tracker with a follow-up reminder.</p>' +
      '<div class="sw-msgs">' + L.map(function (x, i) {
        var on = OUT.sent[i];
        return '<div class="sw-m' + (on ? ' sent' : '') + '"><div class="sw-m-h"><b>@' + esc(x.h) + '</b>' + (x.obs ? '<small>' + esc(x.obs) + '</small>' : '') + '</div>' + (x.miss ? '<p class="sw-msg bad" style="margin:6px 0 0">We found nothing to point at on this page. Replace {what you noticed on their page} before you send.</p>' : '') + (x.msgs && x.msgs.length > 1 ? x.msgs.map(function (m, j) { var id = 'bk-' + i + (j ? '-' + j : ''); return '<small style="display:block;font-weight:700;opacity:.75;margin:8px 0 4px">' + esc(m[0]) + '</small><textarea id="' + id + '" rows="' + Math.min(12, m[1].split('\n').reduce(function (n, l) { return n + Math.max(1, Math.ceil(l.length / 30)); }, 1)) + '">' + esc(m[1]) + '</textarea>' + (j ? '<button type="button" class="sw-s ghost" data-fuc="' + id + '" style="margin-top:6px">Copy message ' + (j + 1) + '</button>' : ''); }).join('') : '<textarea id="bk-' + i + '" rows="' + Math.min(16, x.text.split('\n').reduce(function (n, l) { return n + Math.max(1, Math.ceil(l.length / 30)); }, 1)) + '">' + esc(x.text) + '</textarea>') +
          ((x.fu || []).length ? '<details style="margin:8px 0 2px"><summary style="cursor:pointer;font-weight:700;font-size:13.5px">Follow-ups if they do not reply (day 2 and day 5)</summary>' + x.fu.map(function (f, k2) { return '<div style="margin-top:8px"><small style="font-weight:700;opacity:.7">' + (k2 ? 'Day 5' : 'Day 2') + '</small><textarea id="bkf-' + i + '-' + k2 + '" rows="3">' + esc(f) + '</textarea><button type="button" class="sw-s ghost" data-fuc="bkf-' + i + '-' + k2 + '" style="margin-top:6px">Copy</button></div>'; }).join('') + '</details>' : '') +
          '<div class="sw-m-a"><button type="button" class="sw-s ghost" data-bc="' + i + '">Copy</button><a class="sw-s ig" href="https://ig.me/m/' + esc(x.h) + '" data-bo="' + i + '">' + (on ? '✓ Sent · open again' : 'Open DM') + '</a></div></div>';
      }).join('') + '</div>' +
      '<details class="sw-ai"><summary>✨ Make them sound even more natural <small>AI rewrites all ' + n + '</small></summary><div class="sw-body"><div class="sw-row"><button type="button" class="sw-btn" id="bkAi">Rewrite with AI</button></div><p class="sw-msg" id="bkMsg"></p></div></details>' +
      '<button type="button" class="sw-go" id="bkNext">' + (done >= n ? 'Next 5 people →' : 'Next 5 people (' + done + ' of ' + n + ' sent)') + '</button>' +
      '<button type="button" class="sw-link" id="swAgain">Write for one person instead</button>';
  }
  function bindBulk() {
    $('#swEdit').onclick = function () { ST.view = 'wiz'; ST.step = 2; OUT = null; draw(); window.scrollTo(0, 0); };
    $('#swAgain').onclick = function () { ST.bulk = '0'; PR.blist = ''; ST.view = 'wiz'; ST.step = 1; OUT = null; draw(); window.scrollTo(0, 0); };
    $$('[data-bc]').forEach(function (b) { b.onclick = function () { copyText($('#bk-' + b.getAttribute('data-bc')).value.trim(), function () { flash('Copied'); }); }; });
    $$('[data-bo]').forEach(function (a) {
      a.onclick = function () {
        var i = +a.getAttribute('data-bo'), x = OUT.bulk[i], t = $('#bk-' + i).value.trim();
        if (leftover(t)) { return false; }
        copyText(t, function () { }); flash('Message copied. Paste it in the chat.');
        if (!OUT.sent[i]) {
          OUT.sent[i] = 1;
          if (!OUT.added || !OUT.added[i]) addLead({ h: x.h, at: true, pl: 'Instagram', ch: 'dm', ang: x.ap || ('Bulk: ' + (BANG.filter(function (q) { return q[0] === PR.bang; })[0] || BANG[0])[1]), notes: x.obs ? 'Noticed ' + x.obs : '', obs: x.obs, contact: { ig: x.h } });
          setTimeout(function () { var card = a.closest('.sw-m'); if (card) { card.classList.add('sent'); } a.textContent = '✓ Sent · open again'; var g = $('#swRes .sw-goal'); if (g) { g.outerHTML = goalHtml(); } var nb = $('#bkNext'), dn = Object.keys(OUT.sent).length; if (nb) { nb.textContent = dn >= OUT.bulk.length ? 'Next 5 people →' : 'Next 5 people (' + dn + ' of ' + OUT.bulk.length + ' sent)'; } }, 50);
        }
      };
    });
    $('#bkNext').onclick = function () { PR.blist = ''; BKF = ''; BKDONE = null; ST.view = 'wiz'; ST.step = 2; OUT = null; draw(); window.scrollTo(0, 0); var t = $('#bk-list'); if (t) { t.focus(); } };
    $('#bkAi').onclick = function () {
      var b = this, m = $('#bkMsg'), P = normMe(ME), cur = OUT.bulk.map(function (x, i) { return { h: x.h, noticed: x.obs, messages: (x.msgs && x.msgs.length > 1 ? x.msgs : [[0]]).map(function (m, j) { var ta = $('#bk-' + i + (j ? '-' + j : '')); return ta ? ta.value.trim() : ''; }) }; });
      b.disabled = true; m.textContent = 'Rewriting...';
      IRAI.askJSON({ system: 'You rewrite cold Instagram DMs so each one sounds like a real person typed it on their phone. Keep each message\'s meaning, facts and @handle greeting. Make every message clearly different from the others in wording and rhythm. 35 to 70 words each, 2 or 3 short paragraphs, contractions, at most one emoji, no em dashes, no hype, never invent results or details. Each person has a short sequence of separate messages: keep the same number of messages, each doing the same job, in the same order. Reply with JSON only: {"people":[["message 1","message 2"],["..."]]} in the same order as given.',
        user: 'Sender: ' + P.me + ', ' + article(P.job) + ' who helps ' + P.client + ' ' + P.result + '.\nMessages:\n' + JSON.stringify(cur), max: 3000, onStatus: function (x) { m.textContent = x; } }, function (j) { return (j.people || []).length === cur.length; })
        .then(function (j) { j.people.forEach(function (arr, i) { (Array.isArray(arr) ? arr : [arr]).forEach(function (t, k) { var ta = $('#bk-' + i + (k ? '-' + k : '')); if (ta && String(t).trim()) { ta.value = String(t).trim(); if (OUT.bulk[i].msgs && OUT.bulk[i].msgs[k]) { OUT.bulk[i].msgs[k][1] = ta.value; } if (!k) { OUT.bulk[i].text = ta.value; } } }); }); b.disabled = false; m.textContent = 'Done. Each message is rewritten.'; })
        .catch(function (e) { b.disabled = false; m.textContent = e.message; });
    };
  }

  /* ---------- the AI, on the member's device ---------- */
  function aiErr(t) { var er = $('#swErr'); if (er) { er.textContent = t; er.hidden = false; } }
  var SCRIPTS = {};
  function getScripts(ch) {
    if (SCRIPTS[ch]) { return Promise.resolve(SCRIPTS[ch]); }
    return IRAI.post('ir_ct_scripts', { ch: ch }).then(function (j) { SCRIPTS[ch] = (j && j.text) || ''; return SCRIPTS[ch]; }).catch(function () { return ''; });
  }
  function meLines() { var P = normMe(ME), X = (W && W.profile) || {}; return 'What I do: ' + P.job + '\nWho I help: ' + P.client + '\nThe result I get them: ' + P.result + (P.service ? '\nHow I do it: ' + P.service : '') + (X.price ? '\nMy price: ' + X.price : '') + (X.proof || ME.proof ? '\nProof: ' + (X.proof || ME.proof) : '') + (X.book ? '\nWhere they book a call: ' + X.book : ''); }
  function sumHtml() {
    var M = normPr(PR);
    return '<div class="sw-sum"><span>' + (ST.ch === 'email' ? '✉️ Cold email' : '💬 Cold DM') + '</span><span>' + esc(OUT.ap.n) + '</span>' + (M.name || M.biz ? '<span>' + esc(M.name || M.biz) + '</span>' : '') + '</div>';
  }
  function via() { var c = clean(PR.contact); if (ST.ch === 'email') { return emailOk(c) ? 'mail' : ''; } if (PR.via === 'wa') { return waOk(c) ? 'wa' : ''; } return igHandle(c) ? 'ig' : ''; }
  function showOut() {
    var a = OUT.ap, em = ST.ch === 'email', box = $('#swOut'), h;
    if (OUT.bulk) { box.innerHTML = '<section class="sw-card sw-out" id="swRes">' + bulkOut() + '</section>'; bindBulk(); $('#swRes').scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
    if (!logged) { setTimeout(function () { track('auto'); }, 300); }
    if (OUT.reply) { box.innerHTML = '<section class="sw-card sw-out" id="swRes">' + replyOut() + '</section>'; bindOut(true); $('#swRes').scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
    var head = '<div class="sw-out-h"><h3>✓ Your ' + (em ? 'email' : OUT.msgs && OUT.msgs.length > 1 ? 'messages are' : 'message') + (em || (OUT.msgs && OUT.msgs.length === 1) ? ' is' : '') + ' ready</h3><button type="button" class="sw-link" id="swEdit">Edit answers</button></div>' + sumHtml();
    if (em) {
      h = head + '<label class="sw-f"><span>Subject</span><input id="swSub" value="' + esc(OUT.subject) + '"></label>' +
        '<label class="sw-f"><span>Message. You can edit it here.</span><textarea id="swBody" rows="11">' + esc(OUT.body) + '</textarea></label>' +
        '<p class="sw-count" id="swCount"></p>' + (a.note ? '<p class="sw-note">Before you send: ' + esc(a.note) + '</p>' : '') + '<div class="sw-send" id="swSend"></div>';
    } else {
      h = head + (via() ? '' : '<p class="sw-hint">Add their WhatsApp or Instagram (tap Edit answers) to send in one tap. You can still copy each message.</p>') + '<div class="sw-msgs">' +
        OUT.msgs.map(function (m, i) { return msgCard(m, i, 'm'); }).join('') + '</div>' +
        (OUT.br.length ? '<div class="sw-brs"><div class="sw-lab">If they reply</div>' + OUT.br.map(function (m, i) { return '<details class="sw-br"><summary>' + esc(m[0]) + '</summary>' + msgCard(['Your reply', m[1]], i, 'b') + '</details>'; }).join('') + '</div>' : '') +
        (OUT.msgs.length > 1 ? '<button type="button" class="sw-s ghost sw-all" id="swAll">Copy all messages</button>' : '');
    }
    h += '<p class="sw-hint" style="margin:10px 0 0">✓ ' + (LEAD ? 'This follow-up is logged in your tracker automatically.' : 'Added to your tracker automatically, with a follow-up reminder.') + '</p>';
    if (em || OUT.msgs.length === 1) {
      h += '<details class="sw-ai"><summary>✨ Polish with AI <small>Change the tone or length</small></summary><div class="sw-body"><div class="sw-two"><label class="sw-f"><span>Tone</span><select id="ai-tone"><option value="">Friendly</option><option value="professional">Professional</option><option value="bold">Bold and direct</option><option value="casual">Casual</option><option value="warm">Warm and personal</option></select></label><label class="sw-f"><span>Length</span><select id="ai-len"><option value="">Same length</option><option value="shorter">Shorter</option><option value="longer">A bit longer</option></select></label></div>' +
        '<label class="sw-f"><span>Anything else? (optional)</span><input id="ai-notes" maxlength="200" placeholder="e.g. mention my free trial"></label><div class="sw-row"><button type="button" class="sw-btn" id="aiGo">Polish my message</button><button type="button" class="sw-btn ghost" id="aiUndo" hidden>Undo</button></div><p class="sw-msg" id="aiMsg"></p></div></details>';
    }
    h += '<button type="button" class="sw-link" id="swAgain">Write for someone new</button>';
    box.innerHTML = '<section class="sw-card sw-out" id="swRes">' + h + '</section>';
    bindOut(false);
    if (em) { renderSend(); counter(); $('#swBody').oninput = function () { counter(); renderSend(); }; $('#swSub').oninput = renderSend; }
    else { $$('[data-mc]').forEach(function (b) { b.onclick = function () { var t = $('#' + b.getAttribute('data-mc')).value.trim(); copyText(t, function () { flash('Copied'); }); track('copy'); }; }); $$('[data-ms]').forEach(function (b) { b.onclick = function () { actDm($('#' + b.getAttribute('data-ms')).value.trim()); }; }); $$('[data-mig]').forEach(function (a) { a.onclick = function () { var t = $('#' + a.getAttribute('data-mig')).value.trim(); copyText(t, function () { }); flash('Message copied. Paste it in the chat.'); track('ig'); }; }); var al = $('#swAll'); if (al) { al.onclick = function () { copyText($$('#swOut .sw-msgs textarea').map(function (t) { return t.value.trim(); }).join('\n\n'), function () { flash('All messages copied'); }); }; } }
    var go = $('#aiGo'); if (go) { go.onclick = polish; $('#aiUndo').onclick = function () { if (prev) { setMain(prev.subject, prev.body); prev = null; this.hidden = true; if (em) { counter(); renderSend(); } } }; }
    $('#swRes').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  function bindOut(rep) {
    $('#swEdit').onclick = function () { ST.view = 'wiz'; ST.step = isReply() ? 2 : 3; OUT = null; draw(); window.scrollTo(0, 0); };
    $('#swAgain').onclick = function () { PR.name = ''; PR.biz = ''; PR.obs = ''; PR.impact = ''; PR.like = ''; PR.tip = ''; PR.contact = ''; PR.reply = ''; PR.ctx = ''; SCAN = null; SHOTS = []; LEAD = null; ST.view = 'wiz'; ST.step = isReply() ? 2 : 2; OUT = null; history.replaceState(null, '', location.pathname); draw(); window.scrollTo(0, 0); };
    if (!rep) { return; }
    $$('[data-mc]').forEach(function (b) { b.onclick = function () { var t = $('#' + b.getAttribute('data-mc')).value.trim(); copyText(t, function () { flash('Copied'); }); track('copy'); }; });
    $$('[data-ms]').forEach(function (b) { b.onclick = function () { actDm($('#' + b.getAttribute('data-ms')).value.trim()); }; });
    $$('[data-mig]').forEach(function (a) { a.onclick = function () { var t = $('#' + a.getAttribute('data-mig')).value.trim(); copyText(t, function () { }); flash('Message copied. Paste it in the chat.'); track('ig'); }; });
    $$('[data-mm]').forEach(function (b) { b.onclick = function () { var t = $('#' + b.getAttribute('data-mm')).value.trim(); if (!t) { return; } LASTSENT = t; track('mail'); window.location.href = 'mailto:' + clean(PR.contact) + '?subject=' + enc('Re: ' + (LEAD && LEAD.biz ? LEAD.biz : 'our chat')) + '&body=' + enc(t); }; });
  }
  function msgCard(m, i, p) {
    var id = 'sw-' + p + i, parts = String(m[0]).split(' · '), v = via();
    return '<div class="sw-m"><div class="sw-m-h"><b>' + esc(parts[0]) + '</b>' + (parts[1] ? '<small>' + esc(parts[1]) + '</small>' : '') + '</div><textarea id="' + id + '" rows="' + Math.min(14, Math.max(3, Math.ceil(m[1].length / 32) + (m[1].split('\n').length - 1))) + '">' + esc(m[1]) + '</textarea><div class="sw-m-a"><button type="button" class="sw-s ghost" data-mc="' + id + '">Copy</button>' + (v === 'mail' ? '<button type="button" class="sw-s pri" data-mm="' + id + '">Open in my email app</button>' : v === 'wa' ? '<button type="button" class="sw-s wa" data-ms="' + id + '">Send on WhatsApp</button>' : v === 'ig' ? '<a class="sw-s ig" href="https://ig.me/m/' + esc(igHandle(clean(PR.contact))) + '" data-mig="' + id + '">Open the DM in Instagram</a>' : '') + '</div></div>';
  }
  function setMain(subject, body) { if ($('#swSub')) { $('#swSub').value = subject; } var t = $('#swBody') || $('#sw-m0'); if (t) { t.value = body; } }
  function cur() { var t = $('#swBody') || $('#sw-m0'); return { subject: $('#swSub') ? $('#swSub').value.trim() : '', body: t ? t.value.trim() : '' }; }
  function counter() {
    var t = cur().body, n = t ? t.split(/\s+/).length : 0, el = $('#swCount'), s = n + ' words';
    if (/[{}]/.test(t)) { s += ' · Fix the { } parts before you send.'; }
    if (el) { el.textContent = s; }
  }
  function renderSend() {
    var el = $('#swSend'), okE = via() === 'mail', o = [];
    if (okE) { o.push(['mail', '✉️ Open in my email app', 'pri']); o.push(['gmail', 'Open in Gmail', '']); }
    o.push(['copy', 'Copy message', 'ghost']); o.push(['sent', "I've sent it", 'ghost']);
    el.innerHTML = o.map(function (b) { return '<button type="button" class="sw-s ' + b[2] + '" data-s="' + b[0] + '">' + b[1] + '</button>'; }).join('') + (okE ? '' : '<p class="sw-hint">Add their email address (tap Edit answers) to send this in one tap.</p>');
    $$('[data-s]', el).forEach(function (b) { b.onclick = function () { act(b.getAttribute('data-s')); }; });
  }
  function enc(s) { return encodeURIComponent(s).replace(/%0A/g, '%0D%0A'); }
  function open(url) { var w = null; try { w = window.open(url, '_blank', 'noopener'); } catch (e) { } if (!w) { window.location.href = url; } }
  /* open the app straight away; fall back to the web page only if the app did not open */
  function openApp(app, web) {
    var left = false, h = function () { if (document.hidden) { left = true; } };
    document.addEventListener('visibilitychange', h); window.addEventListener('pagehide', h);
    window.location.href = app;
    setTimeout(function () { document.removeEventListener('visibilitychange', h); window.removeEventListener('pagehide', h); if (!left && !document.hidden) { window.location.href = web; } }, 1600);
  }
  function actIgProfile(text) {
    if (!text) { flash('The message is empty.'); return; }
    var to = clean(PR.contact);
    copyText(text, function () { flash('Message copied. In Instagram tap Message, then paste.'); }); track('ig'); openApp('instagram://user?username=' + igHandle(to), 'https://instagram.com/' + igHandle(to));
  }
  function leftover(t) { return /[{}]/.test(t) && !confirm('This message still has { } parts to fill in. Send anyway?'); }
  function act(k) {
    var c = cur();
    if (!c.body) { flash('The message is empty.'); return; }
    if (k === 'copy') { copyText(c.body, function () { flash('Copied. Paste it where you want to send it.'); }); return; }
    if (leftover(c.body)) { return; }
    var to = clean(PR.contact);
    if (k === 'sent') { track('manual'); return; }
    if (k === 'mail') { track('mail'); window.location.href = 'mailto:' + to + '?subject=' + enc(c.subject) + '&body=' + enc(c.body); return; }
    if (k === 'gmail') { track('mail'); var web = 'https://mail.google.com/mail/?view=cm&fs=1&to=' + encodeURIComponent(to) + '&su=' + encodeURIComponent(c.subject) + '&body=' + encodeURIComponent(c.body); if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) { openApp('googlegmail:///co?to=' + encodeURIComponent(to) + '&subject=' + encodeURIComponent(c.subject) + '&body=' + encodeURIComponent(c.body), web); } else if (/Android/i.test(navigator.userAgent)) { window.location.href = 'mailto:' + to + '?subject=' + enc(c.subject) + '&body=' + enc(c.body); } else { open(web); } return; }
  }
  function actDm(text) {
    if (!text) { flash('The message is empty.'); return; }
    if (leftover(text)) { return; }
    var v = via(), to = clean(PR.contact);
    if (v === 'wa') { track('wa'); openApp('whatsapp://send?phone=' + digits(to) + '&text=' + encodeURIComponent(text), 'https://wa.me/' + digits(to) + '?text=' + encodeURIComponent(text)); }
    else if (v === 'ig') { copyText(text, function () { flash('Message copied. If the chat opens empty, just paste it.'); }); track('ig'); window.location.href = 'https://ig.me/m/' + igHandle(to) + '?text=' + encodeURIComponent(text); }
  }
  function track(method) {
    if (logged) { return; }
    logged = true;
    if (OUT && OUT.reply) {
      var d = OUT.reply, said = 'They said: "' + clean(PR.reply).slice(0, 140) + '"';
      if (LEAD) { LEAD.notes = (LEAD.notes ? LEAD.notes + '\n' : '') + said; addLog(LEAD, 'them', PR.reply); addLog(LEAD, 'me', sentText()); save(); setStage(LEAD, d.stage); flash('Tracker updated · ' + (STAGE[d.stage] || STAGES[2])[1]); return; }
      var M0 = normPr(PR), c0 = clean(PR.contact), ig0 = ST.ch === 'dm' && PR.via === 'ig' ? igHandle(c0) : '', wa0 = ST.ch === 'dm' && PR.via === 'wa' ? digits(c0) : '';
      var l0 = addLead({ h: ST.ch === 'email' ? (c0 || M0.name || M0.biz || 'Prospect') : (ig0 || M0.name || M0.biz || wa0 || 'Prospect'), at: !!ig0, pl: method === 'wa' ? 'WhatsApp' : method === 'ig' ? 'Instagram' : ST.ch === 'email' ? 'Email' : 'Instagram', ch: ST.ch === 'email' ? 'email' : 'dm', notes: said, name: M0.name, biz: M0.biz, contact: { email: ST.ch === 'email' ? c0 : '', wa: wa0, ig: ig0 } });
      addLog(l0, 'them', PR.reply); addLog(l0, 'me', sentText()); save(); setStage(l0, d.stage); flash('Added to your tracker · ' + (STAGE[d.stage] || STAGES[2])[1]); return;
    }
    if (LEAD) { addLog(LEAD, 'me', sentText()); logFollowUp(LEAD); flash('Follow-up logged' + (LEAD.next ? ' · next ' + nice(LEAD.next) : '')); return; }
    var em = ST.ch === 'email', M = normPr(PR), c = clean(PR.contact), ig = PR.via === 'ig' ? igHandle(c) : (em ? '' : igHandle(PR.handle || '')), wa = PR.via === 'wa' ? digits(c) : '', mail = em ? c : '';
    var pl = method === 'wa' ? 'WhatsApp' : method === 'ig' ? 'Instagram' : method === 'mail' ? 'Email' : (em ? 'Email' : (PR.via === 'wa' ? 'WhatsApp' : 'Instagram'));
    var h, at = false;
    if (em) { h = mail || M.name || M.biz || 'Prospect'; }
    else if (ig) { h = ig; at = true; }
    else { h = M.name || M.biz || wa || 'Prospect'; }
    var l = addLead({ h: h, at: at, pl: pl, ch: em ? 'email' : 'dm', ang: OUT.ap.n, notes: M.obs ? 'Noticed ' + M.obs : '', name: M.name, biz: M.biz, obs: PR.obs, like: PR.like || '', log: [{ w: 'me', t: allSent(), d: today() }], contact: { email: mail, wa: wa, ig: ig } });
    flash('Added to your tracker · follow-up due ' + nice(l.next || ''));
  }

  function polish() {
    var m = $('#aiMsg'), b = $('#aiGo'), c = cur(), em = ST.ch === 'email';
    if (!c.body) { return; }
    if (!window.IRAI) { m.textContent = 'Please refresh the page and try again.'; return; }
    var text = em ? 'Subject: ' + c.subject + '\n\n' + c.body : c.body, tone = $('#ai-tone').value || 'friendly', len = $('#ai-len').value, notes = clean($('#ai-notes').value);
    b.disabled = true; m.textContent = 'Polishing...';
    IRAI.ask({ system: 'You rewrite outreach messages so they fit one specific freelancer and sound like a friendly, sensible person typed them. Keep every fact, name, @handle, link and placeholder. Keep the structure and the ask. Contractions, short sentences, no hype, no em dashes, never invent results. ' + (em ? 'Start your reply with "Subject: " and the subject line, then a blank line, then the email.' : 'Keep the blank lines between messages.') + ' Reply with the rewritten message only.',
      user: meLines() + '\nTone: ' + tone + '\nLength: ' + (len || 'same length') + (notes ? '\nAlso: ' + notes : '') + '\n\nThe message:\n<<<\n' + text + '\n>>>', max: 2500, onStatus: function (x) { m.textContent = x; } })
      .then(function (t) {
        t = String(t).trim().replace(/^<<<\s*|\s*>>>$/g, '').replace(/\s*[—–]\s*/g, ', '); var subj = c.subject;
        if (em) { var mm = t.match(/^subject:\s*(.+)\n+/i); if (mm) { subj = mm[1].trim(); t = t.slice(mm[0].length).trim(); } }
        prev = { subject: c.subject, body: c.body }; setMain(subj, t);
        b.disabled = false; m.textContent = 'Done. You can still edit it, or tap Undo.'; $('#aiUndo').hidden = false; if (em) { counter(); renderSend(); }
      }).catch(function (e) { b.disabled = false; m.textContent = e.message; });
  }

  /* ---------- tracker ---------- */
  var filter = 'all', chf = 'all', q = '';
  /* What is working: the pipeline as a funnel and the reply rate of each approach (shown once there are a few prospects). */
  function stLabel(l) { return String((STAGE[l.st] || [])[1] || ''); }
  function gotReply(l) { var t = stLabel(l); return /repl|interest|call|client/i.test(t) && !/not/i.test(t); }
  function insights() {
    var L = S.leads; if (L.length < 3) { return ''; }
    var rep = L.filter(gotReply).length, call = L.filter(function (l) { return /call|client/i.test(stLabel(l)); }).length, cl = L.filter(function (l) { return /client/i.test(stLabel(l)); }).length;
    var pc = function (a, b) { return b ? Math.round(a / b * 100) + '%' : '0%'; };
    var by = {}; L.forEach(function (l) { var k = l.ang || ''; if (!k) { return; } by[k] = by[k] || [0, 0]; by[k][0]++; if (gotReply(l)) { by[k][1]++; } });
    var rows = Object.keys(by).filter(function (k) { return by[k][0] >= 3; }).map(function (k) { return [k, by[k][0], Math.round(by[k][1] / by[k][0] * 100)]; }).sort(function (a, b) { return b[2] - a[2]; }).slice(0, 5);
    return '<div class="pbt-card"><div class="pbt-card-h"><b>What is working</b><small>From your ' + L.length + ' prospects</small></div>' +
      '<div class="pbt-kpis"><div><b>' + L.length + '</b><small>Messaged</small></div><div><b>' + rep + '</b><small>Replied <em>' + pc(rep, L.length) + ' of messaged</em></small></div><div><b>' + call + '</b><small>Calls booked <em>' + pc(call, rep) + ' of replies</em></small></div><div><b>' + cl + '</b><small>Clients <em>' + pc(cl, call) + ' of calls</em></small></div></div>' +
      (rows.length ? '<div style="margin-top:14px;display:grid;gap:9px">' + rows.map(function (r, i) { return '<div style="display:grid;grid-template-columns:minmax(0,1.3fr) 2fr auto;gap:10px;align-items:center;font-size:14px"><span style="font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc(r[0]) + '</span><span style="height:8px;border-radius:9px;background:var(--line,rgba(127,127,127,.2));overflow:hidden"><i style="display:block;height:100%;width:' + Math.max(3, r[2]) + '%;border-radius:9px;background:' + (i === 0 ? '#16a34a' : 'var(--acc,#3b82f6)') + '"></i></span><span style="font-weight:800;white-space:nowrap">' + r[2] + '% <small style="font-weight:500;opacity:.7">of ' + r[1] + '</small></span></div>'; }).join('') + '</div><div class="pbt-tip ok" style="margin-top:12px">' + (rows[0][2] > 0 ? 'Your best opener so far is ' + esc(rows[0][0]) + '. Use it more this week.' : 'No replies yet on these approaches. Try a different one for your next 10 messages.') + '</div>' : '<p class="pbt-empty" style="margin-top:10px">Use the same approach on 3 or more prospects and its reply rate shows here.</p>') + '</div>';
  }
  function exportCsv() {
    var q2 = function (v) { return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"'; };
    var out = [['Prospect', 'Business', 'Platform', 'Type', 'Stage', 'Approach', 'First sent', 'Follow-ups', 'Next follow-up', 'Notes'].join(',')];
    live().forEach(function (l) { out.push([l.h, l.biz || '', l.pl || '', isEmail(l) ? 'Email' : 'DM', stLabel(l), l.ang || '', l.sent || '', l.fu || 0, l.next || '', l.notes || ''].map(q2).join(',')); });
    var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + out.join('\r\n')], { type: 'text/csv' })); a.download = 'my-prospects.csv'; document.body.appendChild(a); a.click(); a.remove();
  }
  /* ---------- one scorecard: this week or this month, compared with the one before, plus what to improve ---------- */
  var SCP = 'week', TEL = null;
  function dUTC(s) { return new Date(s + 'T00:00:00Z'); }
  function fmtD(dt) { return dt.toISOString().slice(0, 10); }
  function span(from, to) {
    var t = { sent: 0, fu: 0, rep: 0, call: 0, cl: 0, act: 0, n: 0, days: [] };
    for (var d = from; d <= to; d = addDays(d, 1)) { var x = S.days[d] || {}, s = total(x); t.sent += s; t.fu += x.fu || 0; t.rep += x.rep || 0; t.call += x.call || 0; t.cl += x.cl || 0; if (s || x.fu) { t.act++; } t.n++; t.days.push([d, s, x.fu || 0]); if (t.n > 40) { break; } }
    return t;
  }
  function periods() {
    var td = today(), dow = (dUTC(td).getUTCDay() + 6) % 7;
    if (SCP === 'week') { var ws = addDays(td, -dow); return { cur: span(ws, td), prev: span(addDays(ws, -7), addDays(ws, -1)), full: 7, label: 'this week', plabel: 'last week' }; }
    var ms = td.slice(0, 8) + '01', pe = addDays(ms, -1), ps = pe.slice(0, 8) + '01', mdays = new Date(Date.UTC(+td.slice(0, 4), +td.slice(5, 7), 0)).getUTCDate();
    return { cur: span(ms, td), prev: span(ps, pe), full: mdays, label: 'this month', plabel: 'last month' };
  }
  function pct(a, b) { return b ? Math.round(a / b * 100) : 0; }
  function delta(a, b) { if (!a && !b) { return '<em class="sc-d">–</em>'; } var x = a - b; return x > 0 ? '<em class="sc-d up">▲ ' + x + '</em>' : x < 0 ? '<em class="sc-d down">▼ ' + (-x) + '</em>' : '<em class="sc-d">same</em>'; }
  function bestOpener() {
    var by = {}; S.leads.forEach(function (l) { var k = l.ang || ''; if (!k) { return; } by[k] = by[k] || [0, 0]; by[k][0]++; if (gotReply(l)) { by[k][1]++; } });
    var r = Object.keys(by).filter(function (k) { return by[k][0] >= 3; }).map(function (k) { return [k, by[k][0], pct(by[k][1], by[k][0])]; }).sort(function (a, b) { return b[2] - a[2]; });
    return r.length && r[0][2] > 0 ? r[0] : null;
  }
  /* The 3 things that will move the numbers most, in order. */
  function improve(P) {
    var c = P.cur, el = c.n, G = S.goal || 15, tips = [], rate = pct(c.rep, c.sent), best = bestOpener();
    var L = function (slug) { return C.base + slug + '/'; };
    if (c.sent < G * el * 0.6) { tips.push(['📤', 'Send more', 'You sent ' + c.sent + ' ' + P.label + '. Your goal of ' + G + ' a day means about ' + (G * el) + ' by now. Clients come from volume first.', C.base + 'writer/?bulk=1', 'Send 5 DMs at once']); }
    if (el >= 3 && c.act < el * 0.6) { tips.push(['📅', 'Show up every day', 'You sent messages on ' + c.act + ' of ' + el + ' days. Ten messages every day beat fifty once a week.', C.base + 'writer/', 'Write today\'s messages']); }
    if (c.sent >= 10 && c.fu < c.sent * 0.5) { tips.push(['🔁', 'Follow up more', 'Only ' + c.fu + ' follow-ups for ' + c.sent + ' new messages. Most replies come on the 2nd or 3rd message.', L('follow-up-strategies'), 'Follow-up lesson']); }
    if (c.sent >= 15 && rate < 10) { tips.push(['🎯', 'Make your openers sharper', 'Your reply rate is ' + rate + '%. Point out one real problem you saw on their page' + (best ? ', like your best opener ' + best[0] + ' (' + best[2] + '% replies)' : '') + '.', L('outreach-audit-diagnose-why-your-dms-aren-t-working'), 'Run the outreach audit']); }
    if (c.rep >= 3 && c.call < c.rep * 0.25) { tips.push(['📞', 'Turn replies into calls', c.rep + ' replies but ' + c.call + ' call' + (c.call === 1 ? '' : 's') + '. After 2 or 3 messages, ask for a 10 minute call.', C.base + 'writer/?reply=1', 'Reply to a lead']); }
    if (c.call >= 2 && !c.cl) { tips.push(['🤝', 'Close your calls', c.call + ' calls and no client yet. Follow the discovery call script word for word.', L('discovery-call-script-from-call-to-closed-client'), 'Discovery call script']); }
    if (!tips.length) { tips.push(['🚀', 'Raise the bar', 'Strong ' + (SCP === 'week' ? 'week' : 'month') + '. Add 5 to your daily goal and keep the streak going.', C.base + 'writer/?bulk=1', 'Send more']); }
    return tips.slice(0, 3);
  }
  function scorecard() {
    var P = periods(), c = P.cur, p = P.prev, G = S.goal || 15, target = G * P.full, pace = G * c.n, mx = 1, bars = [];
    if (SCP === 'week') { var ws = c.days.length ? c.days[0][0] : today(); for (var i = 0; i < 7; i++) { var dd = addDays(ws, i), x = S.days[dd] || {}; bars.push([['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][i], total(x), x.fu || 0, dd === today(), dd > today()]); } }
    else { for (var w = 0; w < c.days.length; w += 7) { var s1 = 0, f1 = 0; c.days.slice(w, w + 7).forEach(function (z) { s1 += z[1]; f1 += z[2]; }); bars.push(['Week ' + (w / 7 + 1), s1, f1, w + 7 >= c.days.length, false]); } }
    bars.forEach(function (b) { mx = Math.max(mx, b[1] + b[2]); });
    var best = bestOpener(), tips = improve(P), td = today(), dom = +td.slice(8, 10), review = '', gp = Math.min(100, pct(c.sent, target)), pp = Math.min(100, pct(pace, target));
    if (SCP === 'month' && dom <= 7 && (p.sent || p.fu)) { var pm = dUTC(addDays(td.slice(0, 8) + '01', -1)).toLocaleDateString('en-GB', { month: 'long', timeZone: 'UTC' }); review = '<div class="sc-review"><b>📋 Your ' + esc(pm) + ' review</b><p>' + p.sent + ' messages, ' + p.fu + ' follow-ups, ' + p.rep + ' replies (' + pct(p.rep, p.sent) + '%), ' + p.call + ' calls and ' + p.cl + ' client' + (p.cl === 1 ? '' : 's') + '. Below is what to work on this month.</p></div>'; }
    var on = c.sent >= pace, behind = pace - c.sent;
    var chip = function (a, b) { if (!a && !b) { return '<span class="k-d">–</span>'; } var x = a - b; return x > 0 ? '<span class="k-d up">▲ ' + x + '</span>' : x < 0 ? '<span class="k-d down">▼ ' + (-x) + '</span>' : '<span class="k-d">same</span>'; };
    var K = [['🔁', 'Follow-ups', c.fu, p.fu, c.sent ? Math.round(c.fu / Math.max(1, c.sent) * 10) / 10 + ' per message' : 'chase every lead'], ['📩', 'Replies', c.rep, p.rep, pct(c.rep, c.sent) + '% reply rate'], ['📞', 'Calls booked', c.call, p.call, pct(c.call, c.rep) + '% of replies'], ['🤝', 'Clients won', c.cl, p.cl, pct(c.cl, c.call) + '% of calls']];
    return '<section class="sc sc2" id="scorecard"><div class="sc-h"><div><h2>Scorecard</h2><p>Updates on its own as you work</p></div><div class="sc-seg" role="tablist"><button type="button" role="tab" data-sp="week" class="' + (SCP === 'week' ? 'on' : '') + '">Week</button><button type="button" role="tab" data-sp="month" class="' + (SCP === 'month' ? 'on' : '') + '">Month</button></div></div>' +
      review +
      '<div class="sc2-hero"><div class="sc2-hl"><span class="sc2-lab">Messages sent ' + P.label + '</span><span class="sc2-st ' + (on ? 'ok' : 'warn') + '">' + (on ? '✓ On pace' : behind + ' behind pace') + '</span></div>' +
      '<div class="sc2-num"><b>' + c.sent + '</b><span>/ ' + target + '</span>' + chip(c.sent, p.sent) + '</div>' +
      '<div class="sc-prog"><i style="width:' + gp + '%"></i><em style="left:' + pp + '%" title="Where you should be today"></em></div>' +
      '<div class="sc2-sub"><span>' + gp + '% of goal · ' + G + ' a day</span><span>Active ' + c.act + ' of ' + c.n + ' days</span></div></div>' +
      '<div class="sc2-kh"><span>Results</span><small>▲▼ vs ' + P.plabel + '</small></div>' +
      '<div class="sc2-k">' + K.map(function (k) { return '<div class="sc2-t"><div class="sc2-tt"><span class="sc2-ic">' + k[0] + '</span>' + chip(k[2], k[3]) + '</div><b>' + k[2] + '</b><small>' + k[1] + '</small><em>' + k[4] + '</em></div>'; }).join('') + '</div>' +
      '<div class="sc2-chart"><div class="sc2-kh"><span>' + (SCP === 'week' ? 'Daily activity' : 'Weekly activity') + '</span><span class="sc-leg"><span><i class="dm"></i>New</span><span><i class="fu"></i>Follow-ups</span></span></div>' +
      '<div class="sc-bars">' + bars.map(function (b) { return '<div class="sc-bar' + (b[3] ? ' t' : '') + (b[4] ? ' f' : '') + '"><em>' + (b[1] + b[2] || '') + '</em><div class="st"><i class="fu" style="height:' + (b[2] / mx * 100) + '%"></i><i class="dm" style="height:' + (b[1] / mx * 100) + '%"></i></div><small>' + b[0] + '</small></div>'; }).join('') + '</div></div>' +
      (best ? '<div class="sc2-best"><span>🏆</span><p>Best opener: <b>' + esc(best[0]) + '</b> gets ' + best[2] + '% replies. Use it more.</p></div>' : '') +
      '<div class="sc2-kh sc2-fh"><span>Focus on next</span></div>' +
      '<div class="sc2-imp">' + tips.map(function (t, i) { return '<a class="sc2-tip' + (i ? '' : ' top') + '" href="' + esc(t[3]) + '"><span class="n">' + (i + 1) + '</span><span class="tx"><b>' + esc(t[1]) + '</b><small>' + esc(t[2]) + '</small><em>' + esc(t[4]) + ' →</em></span></a>'; }).join('') + '</div></section>';
  }
  document.addEventListener('click', function (ev) { var b = ev.target.closest && ev.target.closest('[data-sp]'); if (b && TEL) { ev.preventDefault(); var np = b.getAttribute('data-sp'); if (np === SCP) { return; } SCP = np; var s = document.getElementById('scorecard'), y = window.pageYOffset; if (s) { var h = s.offsetHeight; s.style.minHeight = h + 'px'; var t = document.createElement('div'); t.innerHTML = scorecard(); var n = t.firstChild; n.style.minHeight = h + 'px'; s.parentNode.replaceChild(n, s); requestAnimationFrame(function () { n.style.minHeight = ''; }); } else { tracker(TEL); } if (window.pageXOffset) { window.scrollTo(0, y); } } });
  function tracker(el) {
    TEL = el;
    var d = day(), tot = total(d), circ = 2 * Math.PI * 34, pct = Math.min(1, tot / (S.goal || 1));
    var sent7 = sum('dm', 7) + sum('em', 7), sent30 = sum('dm', 30) + sum('em', 30), rep30 = sum('rep', 30), rate = sent30 ? Math.round(rep30 / sent30 * 100) : 0;
    var due = S.leads.filter(function (l) { return l.next && l.next <= today(); });
    var bars = [], max = 1; for (var i = 13; i >= 0; i--) { var dd = addDays(today(), -i), x = S.days[dd] || {}; bars.push([dd, total(x), x.fu || 0]); max = Math.max(max, total(x) + (x.fu || 0), S.goal); }
    var CN = [['dm', 'DMs sent', '💬'], ['em', 'Emails sent', '✉️'], ['fu', 'Follow-ups', '🔁'], ['rep', 'Replies', '📩'], ['call', 'Calls booked', '📞'], ['cl', 'Clients won', '🎉']];
    el.innerHTML =
      '<section class="pbt-today">' +
      '<div class="pbt-top"><div class="pbt-ringw"><svg viewBox="0 0 80 80" class="pbt-ring"><circle cx="40" cy="40" r="34"/><circle class="v" cx="40" cy="40" r="34" style="stroke-dasharray:' + circ.toFixed(1) + ';stroke-dashoffset:' + (circ * (1 - pct)).toFixed(1) + '"/></svg><div><b>' + tot + '</b><small>of ' + S.goal + '</small></div></div>' +
      '<div class="pbt-hi"><span class="pbt-k">' + new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'short' }) + '</span><h2>' + (greet().replace(/[.\s]+$/, '') || 'Today') + '</h2><p class="pbt-msg">' + (tot >= S.goal ? 'Goal reached. Nice work.' : tot ? (S.goal - tot) + ' more to hit today\'s goal.' : 'Send your first message today.') + '</p></div></div>' +
      '<div class="pbt-goalrow"><span class="pbt-gl">Daily goal</span><div class="pbt-goal"><button type="button" data-goal="-1" aria-label="Lower goal">−</button><b>' + S.goal + '</b><button type="button" data-goal="1" aria-label="Raise goal">+</button></div>' +
      '<div class="pbt-gq">' + [25, 50, 100].map(function (x) { return '<button type="button" data-gq="' + x + '" class="' + (S.goal === x ? 'on' : '') + '">' + x + ' a day</button>'; }).join('') + '</div></div>' +
      '<div class="pbt-streak">🔥 <b>' + streak() + '-day</b> streak · <b>' + sent7 + '</b> sent this week</div>' +
      '<a class="pbt-bulk" href="' + C.base + 'writer/?bulk=1">⚡ Write 5 DMs at once</a>' +
      '<label class="pbt-notify"><input type="checkbox" id="pbtNotify"' + (C.notify !== false ? ' checked' : '') + '><span>Email me at 8am when follow-ups are due</span></label></section>' +
      scorecard() +
      '<div class="pbt-card"><div class="pbt-card-h"><b>Follow-ups due' + (due.length ? ' <span class="pbt-badge">' + due.length + '</span>' : '') + '</b><small>DMs: day 2, 5, 7, 30 · Emails: day 3, 7, 14, 30</small></div>' +
      (due.length ? due.map(function (l) { return '<div class="pbt-due"><span class="h">' + esc(who(l)) + ' <i class="pbt-tag">' + (isEmail(l) ? 'Email' : l.ch === 'call' ? 'Call' : 'DM') + '</i></span><span class="w">' + esc(fuName(l)) + (l.next < today() ? ' · <em>overdue</em>' : '') + '</span><a class="pb-btn" href="' + C.base + 'writer/?lead=' + l.id + '">Write it</a><button type="button" class="pb-btn ghost" data-fu="' + l.id + '">Mark sent</button><a class="pb-btn ghost" href="' + C.base + 'writer/?lead=' + l.id + '&reply=1">They replied</a></div>'; }).join('') : '<p class="pbt-empty">Nothing due. Messages you send from the Smart Writer are added here, and I will remind you when to follow up.</p>') + '</div>' +
      '<div class="pbt-card"><div class="pbt-card-h"><b>My prospects</b><small>' + live().filter(function (l) { return !isDone(l); }).length + ' active · ' + live().filter(isDone).length + ' finished' + (live().length ? ' · <button type="button" id="pbtCsv" style="border:0;background:none;color:inherit;font:inherit;text-decoration:underline;cursor:pointer;padding:0">Download list</button>' : '') + '</small></div>' +
      '<details class="pl-add"><summary>＋ Add someone you messaged outside the app</summary><form class="pbt-add" id="pbtAdd"><input name="h" placeholder="@handle, name or email" required><select name="pl"><option>Instagram</option><option>WhatsApp</option><option>LinkedIn</option><option>X</option><option>TikTok</option><option>Email</option></select><select name="ang"><option value="">Approach used</option><optgroup label="Cold DM">' + APPROACHES.dm.first.map(function (a) { return '<option>' + esc(a.n) + '</option>'; }).join('') + '</optgroup><optgroup label="Cold email">' + APPROACHES.email.first.map(function (a) { return '<option>' + esc(a.n) + '</option>'; }).join('') + '</optgroup></select><button class="pb-btn">+ Add · counts as sent</button></form></details>' +
      '<div class="pbt-filters"><input id="pbtQ" placeholder="Search prospects" value="' + esc(q) + '">' + [['all', 'All'], ['dm', 'DMs'], ['email', 'Emails'], ['call', 'Calls']].map(function (s) { var n = s[0] === 'all' ? live().length : live().filter(function (l) { return (l.ch || 'dm') === s[0]; }).length; return '<button type="button" class="pbt-chip' + (chf === s[0] ? ' on' : '') + '" data-ch="' + s[0] + '">' + s[1] + ' <small>' + n + '</small></button>'; }).join('') + '</div>' +
      '<div class="pbt-filters">' + [['all', 'Active']].concat(STAGES.map(function (s) { return [s[0], s[1]]; })).map(function (s) { var n = s[0] === 'all' ? live().filter(function (l) { return !isDone(l); }).length : live().filter(function (l) { return l.st === s[0]; }).length; return '<button type="button" class="pbt-chip' + (filter === s[0] ? ' on' : '') + '" data-f="' + s[0] + '">' + s[1] + ' <small>' + n + '</small></button>'; }).join('') + '</div>' +
      '<div class="pbt-leads">' + leadsHtml() + '</div><p class="pbt-empty" style="margin-top:8px;font-size:13px">Clients, not interested and ghosted prospects leave this list automatically. Tap their stage above to see them. After 21 days they are removed and only your stats keep them.</p></div>';
    bindTracker(el);
  }
  var LIM = 3;
  function leadsHtml() {
    var list = live().filter(function (l) { return (filter === 'all' ? !isDone(l) : l.st === filter) && (chf === 'all' || (l.ch || 'dm') === chf) && (!q || (l.h + ' ' + (l.biz || '') + ' ' + (l.notes || '')).toLowerCase().indexOf(q.toLowerCase()) > -1); });
    if (!list.length) return '<p class="pbt-empty">' + (live().length ? (filter === 'all' && !q ? 'No active prospects. Everyone is finished: send some new messages.' : 'No prospects match.') : 'No prospects yet. Send a message from the Smart Writer or add one above.') + '</p>';
    var td = today(), pri = function (l) { if (l.next && l.next <= td) { return 0; } if (/^(interested|call|replied)$/.test(l.st)) { return 1; } if (l.st === 'client') { return 3; } if (l.st === 'no') { return 4; } return 2; };
    list = list.slice().sort(function (a, b) { return pri(a) - pri(b) || String(b.sent || '').localeCompare(String(a.sent || '')); });
    var html = list.slice(0, LIM).map(function (l) {
      var s = STAGE[l.st] || STAGES[0], due = l.next && l.next <= td, nm = who(l), ig = l.pl === 'Instagram' && /^@?[\w.]{2,30}$/.test(l.h || '');
      return '<details class="pbt-lead pl" data-id="' + l.id + '"><summary><span class="pl-av" style="--sc:' + s[2] + '">' + esc(String(nm).replace(/^@/, '').charAt(0).toUpperCase() || '?') + '</span>' +
        '<span class="pl-m"><b>' + esc(nm) + '</b><small>' + (l.biz && l.biz !== nm ? esc(l.biz) + ' · ' : '') + (isEmail(l) ? 'Email' : l.ch === 'call' ? 'Call' : 'DM') + ' · ' + nice(l.sent) + (l.fu ? ' · ' + l.fu + ' follow-up' + (l.fu > 1 ? 's' : '') : '') + '</small></span>' +
        '<span class="pl-st' + (due ? ' due' : '') + '" style="--sc:' + s[2] + '">' + (due ? 'Follow up' : esc(s[1])) + '</span></summary>' +
        '<div class="pl-b"><div class="pl-row"><label>Stage <select data-st style="--sc:' + s[2] + '">' + STAGES.map(function (x) { return '<option value="' + x[0] + '"' + (x[0] === l.st ? ' selected' : '') + '>' + x[1] + '</option>'; }).join('') + '</select></label>' + (l.next ? '<span class="pl-next">Next follow-up <b>' + nice(l.next) + '</b></span>' : '') + '</div>' +
        (l.ang || l.obs ? '<p class="pl-info">' + (l.ang ? 'Opened with <b>' + esc(l.ang) + '</b>' : '') + (l.obs ? (l.ang ? ' · ' : '') + 'You noticed: ' + esc(l.obs) : '') + '</p>' : '') +
        '<div class="pl-quick" style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0">' + [['seen', '👀 Seen, no reply'], ['ghost', '🙈 Ghosted'], ['replied', '💬 Replied'], ['interested', '🔥 Interested'], ['call', '📞 Call booked']].map(function (b) { return '<button type="button" class="pbt-chip' + (l.st === b[0] ? ' on' : '') + '" data-qs="' + b[0] + '">' + b[1] + '</button>'; }).join('') + '</div>' +
        ((l.log || []).length ? '<div class="sw-thread" style="margin:6px 0 10px;max-height:260px;overflow:auto">' + l.log.slice(-6).map(function (x) { return '<div class="sw-tb ' + (x.w === 'me' ? 'me' : 'them') + '"><small style="display:block;opacity:.6;font-size:11px">' + (x.w === 'me' ? 'You' : 'Them') + (x.d ? ' · ' + nice(x.d) : '') + '</small>' + esc(String(x.t).slice(0, 300)) + '</div>'; }).join('') + '</div>' : '') +
        '<div class="pl-said" style="display:flex;gap:6px;margin:0 0 10px"><input data-saidt placeholder="Paste what they said to log it" style="flex:1;min-width:0"><button type="button" class="pb-btn ghost" data-said>Log reply</button></div>' +
        '<div class="pl-acts"><a class="pl-a pri" href="' + C.base + 'writer/?lead=' + l.id + '&reply=1">💬 Write my answer</a>' + (l.next ? '<a class="pl-a" href="' + C.base + 'writer/?lead=' + l.id + '">🔁 Write follow-up</a>' : '') + (ig ? '<a class="pl-a" href="https://www.instagram.com/' + esc(String(l.h).replace(/^@/, '')) + '/" target="_blank" rel="noopener">↗ Profile</a>' : '') + '</div>' +
        '<textarea data-notes rows="2" placeholder="Notes: what they said, objections, what you offered">' + esc(l.notes || '') + '</textarea>' +
        '<button type="button" class="pl-del" data-del>Delete</button></div></details>';
    }).join('');
    if (list.length > 3) { html += '<div class="pl-pager"><span>Showing ' + Math.min(LIM, list.length) + ' of ' + list.length + '</span><div>' + (LIM > 3 ? '<button type="button" class="pl-less" data-less>Show less ▴</button>' : '') + (list.length > LIM ? '<button type="button" class="pl-more" data-more>View ' + Math.min(10, list.length - LIM) + ' more ▾</button>' : '') + '</div></div>'; }
    return html;
  }
  document.addEventListener('click', function (ev) { var b = ev.target.closest && ev.target.closest('[data-more]'); if (b && TEL) { LIM += 10; var box = TEL.querySelector('.pbt-leads'); if (box) { box.innerHTML = leadsHtml(); bindLeads(TEL); } } var ls = ev.target.closest && ev.target.closest('[data-less]'); if (ls && TEL) { LIM = 3; var bx = TEL.querySelector('.pbt-leads'); if (bx) { bx.innerHTML = leadsHtml(); bindLeads(TEL); var cd = bx.closest('.pbt-card'); if (cd) { cd.scrollIntoView({ block: 'start', behavior: 'smooth' }); } } } });
  function greet() {
    var n = clean(ME.name || (S.me && S.me.name) || ''), h = new Date().getHours();
    return n ? (h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening') + ', ' + esc(cap1(n.split(' ')[0])) + '. ' : '';
  }
  function bindTracker(el) {
    var nt = $('#pbtNotify', el); if (nt) { nt.onchange = function () { C.notify = nt.checked; var f = new FormData(); f.append('action', 'ir_ct_notify'); f.append('n', C.n); if (nt.checked) { f.append('on', '1'); } fetch(C.aj, { method: 'POST', body: f, credentials: 'same-origin' }).then(function () { flash(nt.checked ? 'Reminders on. We email you at 8am when follow-ups are due.' : 'Reminders off'); }).catch(function () { flash('Not saved. Check your connection.'); }); }; }
    $$('[data-goal]', el).forEach(function (b) { b.onclick = function () { S.goal = Math.max(1, Math.min(300, S.goal + +b.getAttribute('data-goal') * (S.goal >= 50 ? 5 : 1))); save(); tracker(el); }; });
    $$('[data-gq]', el).forEach(function (b) { b.onclick = function () { S.goal = +b.getAttribute('data-gq'); save(); tracker(el); flash('Daily goal set to ' + S.goal); }; });
    $$('[data-c]', el).forEach(function (b) { b.onclick = function () { bump(b.getAttribute('data-c'), +b.getAttribute('data-v')); save(); tracker(el); }; });
    $$('[data-fu]', el).forEach(function (b) { b.onclick = function () { var l = S.leads.filter(function (x) { return x.id === b.getAttribute('data-fu'); })[0]; if (l) { logFollowUp(l); tracker(el); flash('Follow-up logged'); } }; });
    $$('[data-f]', el).forEach(function (b) { b.onclick = function () { filter = b.getAttribute('data-f'); LIM = 3; tracker(el); }; });
    $$('[data-ch]', el).forEach(function (b) { b.onclick = function () { chf = b.getAttribute('data-ch'); LIM = 3; tracker(el); }; });
    var cs = $('#pbtCsv', el); if (cs) { cs.onclick = exportCsv; }
    var qi = $('#pbtQ', el); qi.oninput = function () { q = qi.value; $('.pbt-leads', el).innerHTML = leadsHtml(); bindLeads(el); };
    $('#pbtAdd', el).onsubmit = function (e) {
      e.preventDefault(); var f = e.target.elements, ch = f.pl.value === 'Email' ? 'email' : 'dm', v = f.h.value.trim();
      var l = addLead({ h: v, pl: f.pl.value, ch: ch, ang: f.ang.value, at: ch === 'dm' && /^@?[\w.]+$/.test(v) });
      flash('Prospect added · follow-up due ' + nice(l.next)); tracker(el);
    };
    bindLeads(el);
  }
  function bindLeads(el) {
    $$('.pbt-lead', el).forEach(function (row) {
      var l = S.leads.filter(function (x) { return x.id === row.getAttribute('data-id'); })[0]; if (!l) return;
      $('[data-st]', row).onchange = function (e) { setStage(l, e.target.value); if (e.target.value === 'client') flash('New client! 🎉'); tracker(el); };
      $('[data-del]', row).onclick = function () { if (confirm('Delete ' + who(l) + '?')) { S.leads = S.leads.filter(function (x) { return x !== l; }); save(); tracker(el); } };
      var n = $('[data-notes]', row); n.oninput = function () { l.notes = n.value; save(); };
      $$('[data-qs]', row).forEach(function (b) { b.onclick = function () { var st = b.getAttribute('data-qs'); setStage(l, st); flash(isDone(l) ? who(l) + ' moved out of your active list' : 'Marked ' + stName(l).toLowerCase()); tracker(el); }; });
      var sd = $('[data-said]', row), si = $('[data-saidt]', row); if (sd && si) { var logIt = function () { var t = si.value.trim(); if (!t) { return; } addLog(l, 'them', t); if (['sent', 'seen', 'ghost'].indexOf(l.st) > -1) { setStage(l, 'replied'); } else { save(); } flash('Reply logged'); tracker(el); var op = el.querySelector('.pbt-lead[data-id="' + l.id + '"]'); if (op) { op.open = true; } }; sd.onclick = logIt; si.onkeydown = function (e) { if (e.key === 'Enter') { e.preventDefault(); logIt(); } }; }
    });
  }

  document.addEventListener('click', function (ev) {
    var g = ev.target.closest && ev.target.closest('[data-greet]'); if (g) { readPr(); readMe(); S.greet = g.getAttribute('data-greet'); save(true); meOpen = true; if (root) { draw(); } flash('Greeting saved. It is used in every new message.'); return; }
    var cp = ev.target.closest && ev.target.closest('[data-cp]'); if (cp) { copyText(cp.getAttribute('data-cp'), function () { flash('Copied'); }); }
  });
  /* ---------- mount ---------- */
  if (tidy()) { save(true); }
  var tr = $('#pbTracker'); if (tr) tracker(tr);
  if (root) {
    var sp = new URLSearchParams(location.search), lid = sp.get('lead');
    if (lid) {
      LEAD = S.leads.filter(function (l) { return l.id === lid; })[0] || null;
      if (LEAD) {
        var fi = Math.min(LEAD.fu || 0, 3);
        ST.ch = isEmail(LEAD) ? 'email' : 'dm'; ST.type = sp.get('reply') ? 'reply' : 'fu'; ST.step = sp.get('reply') ? 2 : 3;
        ST.ap = ST.ch === 'email' ? APPROACHES.email.fu[fi].k : ['d-fu2', 'd-fu5', 'd-fu7', 'd-fu30'][fi];
        PR.name = LEAD.name || ''; PR.biz = LEAD.biz || ''; PR.obs = LEAD.obs || '';
        PR.handle = (LEAD.contact && LEAD.contact.ig) ? '@' + LEAD.contact.ig : (LEAD.at && !isEmail(LEAD) ? '@' + LEAD.h : '');
        var ct = LEAD.contact || {};
        if (ST.ch === 'email') { PR.contact = ct.email || (/@/.test(LEAD.h) ? LEAD.h : ''); }
        else if (ct.wa) { PR.via = 'wa'; PR.contact = ct.wa; }
        else if (ct.ig) { PR.via = 'ig'; PR.contact = '@' + ct.ig; }
      }
    }
    if (!LEAD) { var qch = sp.get('ch'); if (qch === 'dm' || qch === 'email') { ST.ch = qch; ST.ap = APPROACHES[qch].first[0].k; persist(); } if (sp.get('reply')) { ST.type = 'reply'; ST.step = 2; } if (sp.get('bulk')) { ST.ch = 'dm'; ST.type = 'first'; ST.bulk = '1'; ST.step = 2; } }
    draw();
  }
  var mini = $('#pbTrkMini');
  if (mini) { var dd = day(), du = S.leads.filter(function (l) { return l.next && l.next <= today(); }).length; mini.innerHTML = '<b>' + total(dd) + ' of ' + S.goal + '</b> sent today' + (streak() ? ' · ' + streak() + '-day streak' : '') + (du ? ' · <em>' + du + ' follow-up' + (du > 1 ? 's' : '') + ' due</em>' : ''); }
  C.smart = { APPROACHES: APPROACHES, normMe: normMe, normPr: normPr, P0: P0, waOk: waOk, emailOk: emailOk, igHandle: igHandle, S: S };
})();

