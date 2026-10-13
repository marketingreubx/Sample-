/* Cold DM Playbook tools: DM tracker + DM writer. Data lives in user meta (ir_pb_trk). */
(function () {
  var C = window.PBT; if (!C) return;
  var S = C.state || {};
  S.goal = S.goal || 15; S.me = S.me || {}; S.days = S.days || {}; S.leads = S.leads || [];

  /* ---------- helpers ---------- */
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return [].slice.call((r || document).querySelectorAll(s)); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function pad(n) { return n < 10 ? '0' + n : '' + n; }
  function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function today() { return ymd(new Date()); }
  function addDays(s, n) { var p = s.split('-'); var d = new Date(+p[0], +p[1] - 1, +p[2]); d.setDate(d.getDate() + n); return ymd(d); }
  function nice(s) { if (!s) return ''; var t = today(); if (s === t) return 'Today'; if (s === addDays(t, 1)) return 'Tomorrow'; if (s === addDays(t, -1)) return 'Yesterday'; var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }); }
  function day(d) { d = d || today(); if (!S.days[d]) S.days[d] = { dm: 0, fu: 0, rep: 0, call: 0, cl: 0 }; return S.days[d]; }
  function bump(k, n) { var d = day(); d[k] = Math.max(0, (d[k] || 0) + (n || 1)); }
  var saveT = null, saving = false;
  function save() {
    clearTimeout(saveT);
    saveT = setTimeout(function () {
      var f = new FormData(); f.append('action', 'ir_pb_trk'); f.append('n', C.n); f.append('data', JSON.stringify(S));
      saving = true; fetch(C.aj, { method: 'POST', body: f, credentials: 'same-origin' }).then(function () { saving = false; flash('Saved'); }).catch(function () { saving = false; flash('Not saved. Check your connection.'); });
    }, 500);
  }
  var toastT;
  function flash(t) { var el = $('#pbToast'); if (!el) { el = document.createElement('div'); el.id = 'pbToast'; el.className = 'pbt-toast'; document.body.appendChild(el); } el.textContent = t; el.classList.add('on'); clearTimeout(toastT); toastT = setTimeout(function () { el.classList.remove('on'); }, 1600); }
  function copy(t, btn) {
    var lab = btn.textContent;
    var done = function () { btn.classList.add('ok'); btn.textContent = 'Copied ✓'; setTimeout(function () { btn.classList.remove('ok'); btn.textContent = lab; }, 1500); };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(t).then(done);
    else { var a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select(); try { document.execCommand('copy'); } catch (e) { } a.remove(); done(); }
  }

  /* ---------- pipeline rules ---------- */
  var STAGES = [
    ['sent', 'DM sent', '#1660E8'], ['seen', 'Seen, no reply', '#7C3AED'], ['replied', 'Replied', '#0891B2'],
    ['interested', 'Interested', '#CA8A04'], ['call', 'Call booked', '#FF7A1A'], ['client', 'Client 🎉', '#16A34A'], ['no', 'Not interested', '#94A3B8']
  ];
  var STAGE = {}; STAGES.forEach(function (s) { STAGE[s[0]] = s; });
  var FU_DAYS = [2, 5, 7, 30];
  var FU_KEY = ['a2', 'a5', 'a7', 'a30'];
  var FU_NAME = ['Follow-up 1 · after 2 days', 'Follow-up 2 · after 5 days', 'Follow-up 3 · after 7 days', 'Follow-up 4 · after a month'];
  function nextFor(l) { if (l.st !== 'sent' && l.st !== 'seen') return ''; var i = l.fu || 0; return i < FU_DAYS.length ? addDays(l.sent, FU_DAYS[i]) : ''; }
  function addLead(h, pl, ang, notes, countDm) {
    var l = { id: Date.now().toString(36) + Math.random().toString(36).slice(2, 6), h: h.replace(/^@/, '').trim(), pl: pl || 'Instagram', st: 'sent', sent: today(), fu: 0, ang: ang || '', notes: notes || '' };
    l.next = nextFor(l); S.leads.unshift(l); if (S.leads.length > 600) S.leads.length = 600;
    if (countDm !== false) bump('dm'); save(); return l;
  }
  function logFollowUp(l) { l.fu = (l.fu || 0) + 1; l.next = nextFor(l); bump('fu'); save(); }
  function setStage(l, st) {
    var order = ['sent', 'seen', 'replied', 'interested', 'call', 'client'];
    var was = order.indexOf(l.st), now = order.indexOf(st);
    if (was < 2 && now >= 2) bump('rep');
    if (was < 4 && now >= 4) bump('call');
    if (was < 5 && now === 5) bump('cl');
    l.st = st; l.next = nextFor(l); save();
  }

  /* ---------- writer data ---------- */
  var F = {
    name: ['Your first name', 'Reuben', 1], role: ['Your role', 'social media manager', 1],
    niche: ['Who you help (plural)', 'estheticians and skincare brands', 1],
    service: ['How you help them', 'a clear content strategy and consistent posting', 1],
    result: ['The result you get them (starts with a verb)', 'get fully booked from Instagram', 1],
    offer: ['What you offer, in plain words', 'a content system that brings in bookings on autopilot', 1],
    pain: ['The problem you solve', 'getting clients consistently from Instagram', 1],
    clients: ['Clients you have worked with', 'skincare brands and med spas in Lagos', 1],
    handle: ['Their @handle or first name', 'glowbyamaka'],
    compliment: ['What you genuinely like on their page', 'the consistency of your skincare reels'],
    praise: ['A genuine compliment (full sentence)', 'Your before-and-after content is so powerful, you can tell your clients love your work.'],
    gap: ['What you noticed that is costing them clients', 'Your bio doesn\'t say what you offer, so people leave your page without booking.'],
    gap2: ['One honest thing they could improve', 'your captions aren\'t pulling people in enough to comment or DM you'],
    observation: ['What you can tell about them', 'serious about building your brand'],
    opportunity: ['What you can help them do', 'turn your engagement into actual bookings'],
    platform: ['Where they get clients from', 'Instagram'], biz: ['Their type of business (plural)', 'skincare brands'],
    pastType: ['A past client like them', 'esthetician'], pastProblem: ['What that client struggled to do', 'get consistent bookings from Instagram'],
    timeframe: ['How long it took', '6 weeks'], pastResult: ['The result you got them (starts with a verb)', 'book 27 new clients'],
    resource: ['The free thing you will send', '5-step Instagram booking checklist'], resourceGoal: ['What it helps them do', 'get more bookings without paid ads'],
    link: ['Link to the free thing', 'https://your-link.com'],
    c1: ['Challenge 1 most of them have', 'Posting consistently but not getting enough inquiries'],
    c2: ['Challenge 2', 'Great at what they do but struggling to show their value online'],
    c3: ['Challenge 3', 'No clear system to turn followers into paying clients'],
    bizword: ['Their business, brand or page?', 'business'], tip: ['A quick, useful tip for them', 'A pinned post that says what you do and how to book you can double your DM inquiries.'],
    firstNoticed: ['What you noticed when you first reached out', 'your page had so much potential'],
    name2: ['Their name', 'Amaka']
  };
  var W = [
    { k: 'problem', g: 'Cold DM openers', n: 'Problem-Focused', l: 'the-problem-focused-approach', f: ['handle', 'gap', 'niche', 'service'], m: [
      ['Message 1', '@{handle} your work is genuinely impressive. You clearly know what you\'re doing and it shows.'],
      ['Message 2 · send right after', 'But I noticed something that might be quietly costing you clients...\n{gap|s}\n\nI help {niche} fix exactly this through {service} so they start attracting ready-to-buy clients without chasing anyone.'],
      ['Message 3 · send separately', 'Would it be okay if I shared 2 quick things you could change right now to start seeing a difference? It\'ll make more sense on a quick call, what\'s your WhatsApp?']] },
    { k: 'result', g: 'Cold DM openers', n: 'Result-Oriented', l: 'result-oriented-approach', f: ['handle', 'compliment', 'pastType', 'pastProblem', 'timeframe', 'pastResult'], m: [
      ['Message 1', '{greet} @{handle} I love {compliment}.'],
      ['Message 2 · send separately', 'I recently worked with {pastType|a} just like you who was struggling to {pastProblem}.\n\nIn {timeframe}, we helped them {pastResult}.'],
      ['Message 3 · send separately', 'I believe the same is possible for you and I\'d love to show you exactly how. It\'s easier to explain on a quick call, what\'s your WhatsApp so I can reach you directly?']] },
    { k: 'value', g: 'Cold DM openers', n: 'Value-Based', l: 'value-based-approach', f: ['handle', 'observation', 'resource', 'niche', 'resourceGoal', 'link'], m: [
      ['Message 1', '{greet} @{handle} I\'ve been following your page for a bit and I can tell you\'re {observation}.'],
      ['Message 2 · send separately', 'I put together a free {resource} specifically for {niche} who want to {resourceGoal}.\n\nWould it be okay if I sent it your way? It\'s completely free, just something I think would genuinely help.']],
      b: [['If they say YES', 'Amazing! Here it is 👇\n{link}\n\nLet me know what you think once you\'ve had a look. And if you want help putting any of it into practice, that\'s exactly what I do. What\'s your WhatsApp? I can walk you through how it would work for your situation.']] },
    { k: 'audit', g: 'Cold DM openers', n: 'Compliment + Quick Audit', l: 'compliment-quick-audit-approach', f: ['handle', 'praise', 'gap2', 'role', 'niche', 'result'], m: [
      ['Message 1', '{greet} @{handle} {praise|s} You\'re doing something right.'],
      ['Message 2 · send separately', 'That said, I noticed {gap2}.\n\nIt\'s a small tweak but it could make a real difference in how many people reach out to you.'],
      ['Message 3 · send separately', 'I\'m {role|a} and I specialize in helping {niche} {result}.\n\nCan I put together a quick 3-point audit for your page, completely free? It\'s easier to walk you through it on a call. What\'s your WhatsApp?']] },
    { k: 'direct', g: 'Cold DM openers', n: 'The Direct Approach', l: 'the-direct-approach', f: ['handle', 'opportunity', 'name', 'role', 'niche', 'result'], m: [
      ['Message 1', '{greet} @{handle} I\'ll keep this short because I know your time is valuable.'],
      ['Message 2 · send separately', 'I\'m {name}, a freelance {role} who specializes in helping {niche} {result}.\n\nI looked at your page and I genuinely believe I can help you {opportunity}.'],
      ['Message 3 · send separately', 'Would you be open to a quick conversation about it? What\'s your WhatsApp so we can link up properly.']] },
    { k: 'soft', g: 'Cold DM openers', n: 'Soft Question Opener', l: 'soft-question-based-opener', f: ['handle', 'compliment', 'platform', 'biz', 'role', 'niche', 'result'], m: [
      ['Message 1', '{greet} @{handle} hope your week is going well. Came across your page and was genuinely impressed by {compliment}.'],
      ['Message 2 · send separately', 'Quick question, are you currently happy with the number of clients or inquiries you\'re getting from your {platform}?\n\nOr do you feel like there\'s more potential there that hasn\'t been tapped yet? Either answer is totally fine, just curious.']],
      b: [['If they say "It could be better"', 'That\'s actually really common for {biz}, you\'re doing great work but the content strategy or visibility piece just isn\'t converting the way it should.\n\nThat\'s exactly what I help with. I\'m {role|a} who works with {niche} to {result}.\n\nIt\'ll make more sense if we speak properly. What\'s your WhatsApp?'],
        ['If they say "Things are going well"', 'That\'s great to hear. If you ever feel like you want to scale that further, feel free to reach out. Happy to support that growth when the time is right.']] },
    { k: 'niche', g: 'Cold DM openers', n: 'Niche-Expertise Opener', l: 'niche-expertise-opener', f: ['handle', 'niche', 'c1', 'c2', 'c3', 'name', 'role', 'service', 'clients'], m: [
      ['Message 1', '{greet} @{handle} I specialize in working exclusively with {niche} so when I came across your page, I immediately noticed a few things.'],
      ['Message 2 · send separately', 'Most {niche} I talk to are dealing with the same 2-3 challenges:\n→ {c1|cap}\n→ {c2|cap}\n→ {c3|cap}\nSound familiar at all?'],
      ['Message 3 · send separately', 'I\'m {name}, a freelance {role} and I help {niche} solve exactly these problems through {service}.\n\nI\'ve worked with {clients} and I know what actually moves the needle.\n\nWould love to show you what that looks like for your business specifically. What\'s your WhatsApp so we can get on a quick call?']] },
    { k: 'notseen', g: 'Follow-ups', fu: 1, n: 'Not Seen', l: 'not-seen', f: ['handle', 'bizword', 'niche', 'result'], m: [
      ['Follow-up', '{greet} {name2}\nJust floating this back to the top in case it got buried, inboxes can be wild!\n\nI reached out because I genuinely think what I do could be a great fit for where you\'re trying to take your {bizword}.\n\nI help {niche} {result}.\n\nWorth a quick chat? No pressure at all. 😊']] },
    { k: 'a2a', g: 'Follow-ups', fu: 1, n: 'After 2 Days · A (light & curious)', l: 'after-2-days', f: ['handle', 'niche', 'result'], m: [
      ['Follow-up', '{greet} {name2}! 👋\nJust bumping this up in case it got lost in the noise, inboxes can be wild 😄\n\nI reached out because I genuinely think there\'s something here worth a quick conversation. I help {niche} {result}, and from what I\'ve seen of your page, I think you\'d be a great fit.\n\nWould love to connect if you\'re open to it! 😊']] },
    { k: 'a2b', g: 'Follow-ups', fu: 1, n: 'After 2 Days · B (lead with a tip)', l: 'after-2-days', f: ['handle', 'niche', 'tip'], m: [
      ['Follow-up', 'Hey {name2}! 👋\nI sent you a message a couple of days ago and just wanted to follow up, no pressure at all!\n\nWhile I\'m here, I wanted to share something quick that\'s been working really well for {niche} right now:\n\n{tip|s}\n\nSomething to try even before we chat! And if you\'d like more tailored strategies for your page specifically, I\'m here. 😊']] },
    { k: 'a5a', g: 'Follow-ups', fu: 1, n: 'After 5 Days · A (reframe with a question)', l: 'after-5-days', f: ['handle', 'bizword'], m: [
      ['Follow-up', '{greet} {name2}! 😊\nI know you\'re probably juggling a lot, I\'ll keep this short.\n\nQuick question: is growing your {bizword} something that\'s on your radar right now, or is your focus somewhere else entirely?\n\nEither answer is genuinely helpful for me to know, no sales pitch, just curious. 🙂']] },
    { k: 'a5b', g: 'Follow-ups', fu: 1, n: 'After 5 Days · B (share a win)', l: 'after-5-days', f: ['handle', 'pastType', 'pastResult', 'bizword'], m: [
      ['Follow-up', '{greet} {name2}! 👋\nJust wanted to share something exciting, I recently helped {pastType|a} {pastResult}.\n\nIt got me thinking about you and what\'s possible for your {bizword} with the right strategy.\n\nStill happy to share what we did, would that be useful? 😊']] },
    { k: 'a5c', g: 'Follow-ups', fu: 1, n: 'After 5 Days · C (honest check-in)', l: 'after-5-days', f: ['handle', 'offer'], m: [
      ['Follow-up', '{greet} {name2}, I\'m going to keep it real with you 😄\nI\'ve followed up a couple of times and haven\'t heard back, which is completely fine! I just don\'t want to keep popping up if it\'s not something you\'re interested in.\n\nIf now isn\'t the right time, no worries at all, just say the word and I\'ll stop following up. But if there\'s even a small part of you that\'s curious about {offer}... I\'d genuinely love to have that conversation. 🙏']] },
    { k: 'a7a', g: 'Follow-ups', fu: 1, n: 'After 7 Days · A (graceful exit)', l: 'after-7-days', f: ['handle', 'bizword'], m: [
      ['Follow-up', '{greet} {name2}! 👋\nI\'ve reached out a few times now and I don\'t want to keep filling up your inbox, I genuinely respect your time 😊\n\nI\'ll make this my last message for a while. If growing your {bizword} becomes a priority down the road, I\'d love to be the one to help you get there.\n\nFeel free to save my contact, the door is always open. Wishing you nothing but growth!']] },
    { k: 'a7b', g: 'Follow-ups', fu: 1, n: 'After 7 Days · B (leave with value)', l: 'after-7-days', f: ['handle', 'tip'], m: [
      ['Follow-up', '{greet} {name2}!\nLast message from me for now, I promise 😄\n\nBefore I go, I wanted to leave you with something useful:\n\n{tip|s}\n\nHope that helps regardless of whether we work together. If you ever want a full strategy session, I\'m just a DM away. 😊\nTake care!']] },
    { k: 'a7c', g: 'Follow-ups', fu: 1, n: 'After 7 Days · C (curiosity closer)', l: 'after-7-days', f: ['handle', 'pain'], m: [
      ['Follow-up', 'Hey {name2}, last one, I promise this isn\'t a guilt trip 😄\nI just want to say: whatever is going on behind the scenes for you right now, I hope it\'s going well.\n\nIf you\'re ever in a season where {pain} becomes the priority, come find me.\n\nI\'ll be here. 🙏 Rooting for you either way!']] },
    { k: 'a30a', g: 'Follow-ups', fu: 1, n: 'After A Month · A (fresh start)', l: 'after-a-month', f: ['handle', 'niche', 'result', 'bizword'], m: [
      ['Follow-up', '{greet} {name2}! 👋\nIt\'s been a little while since I reached out, hope things have been going well on your end! 😊\n\nI\'m still working with {niche} to help them {result}, and I keep thinking your {bizword} has a lot of untapped potential.\n\nWould you be open to a quick chat this month? Even just 10 minutes, no commitment at all.']] },
    { k: 'a30b', g: 'Follow-ups', fu: 1, n: 'After A Month · B (new month energy)', l: 'after-a-month', f: ['handle', 'firstNoticed', 'offer', 'bizword'], m: [
      ['Follow-up', '{greet} {name2}! 😊\nNew month, fresh start! I always find this is when the best decisions get made. 🙌\n\nI wanted to check back in because when we first connected, {firstNoticed}, and I haven\'t stopped believing that.\n\nIf now feels like a better time to explore what {offer} could do for your {bizword}, I\'d love to jump on a quick call.\n\nWhat do you say? 😊']] },
    { k: 'a30c', g: 'Follow-ups', fu: 1, n: 'After A Month · C (share a result)', l: 'after-a-month', f: ['handle', 'pastType', 'pastProblem', 'timeframe', 'pastResult'], m: [
      ['Follow-up', '{greet} {name2}! Long time 😊\nI just wrapped up working with {pastType|a} who was in a similar spot to where you were when we first connected, struggling to {pastProblem}.\n\nIn {timeframe}, we helped them {pastResult}.\n\nIt got me thinking about you again. Still open to a quick conversation? 😊']] }
  ];
  var WK = {}; W.forEach(function (w) { WK[w.k] = w; });
  C.lessonToWriter = {}; W.forEach(function (w) { (C.lessonToWriter[w.l] = C.lessonToWriter[w.l] || []).push(w.k); });

  function greetDefault() { var h = new Date().getHours(); return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening'; }
  function clean(v) { return String(v || '').replace(/\s+/g, ' ').trim(); }
  function nodot(v) { return v.replace(/[.!?,;:\s]+$/, ''); }
  function sentence(v) { v = v.trim(); if (!v) return v; v = v.charAt(0).toUpperCase() + v.slice(1); return /[.!?…)"']$/.test(v) ? v : v + '.'; }
  function article(v) { return (/^[aeiou]/i.test(v) && !/^(uni|use|eu)/i.test(v) ? 'an ' : 'a ') + v; }
  function fill(tpl, vals, html) {
    return tpl.replace(/\{(\w+)(?:\|(\w+))?\}/g, function (_, k, mod) {
      var v = clean(vals[k]);
      if (k === 'handle') v = v.replace(/^@/, '');
      if (!v) { var lab = F[k] ? F[k][0].toLowerCase().replace(/ \(.*\)$/, '') : k; return html ? '<span class="pbt-miss">{' + esc(lab) + '}</span>' : '{' + lab + '}'; }
      if (mod === 's') v = sentence(v);
      else if (mod === 'cap') v = nodot(v.charAt(0).toUpperCase() + v.slice(1));
      else if (mod === 'a') v = article(nodot(v));
      else if (k !== 'link') v = nodot(v);
      return html ? '<b class="pbt-fill">' + esc(v) + '</b>' : v;
    });
  }

  /* ---------- writer UI ---------- */
  function writer(root, opts) {
    opts = opts || {};
    var keys = opts.keys || W.map(function (w) { return w.k; });
    var cur = WK[opts.k] || WK[keys[0]];
    var lead = opts.leadId ? S.leads.filter(function (l) { return l.id === opts.leadId; })[0] : null;
    var P = { handle: lead ? lead.h : (opts.handle || ''), greet: greetDefault(), bizword: 'business', platform: 'Instagram' };
    function vals() { var v = {}; for (var k in S.me) v[k] = S.me[k]; for (var k2 in P) v[k2] = P[k2]; v.name2 = clean(P.handle).replace(/^@/, ''); if (!v.name2) v.name2 = ''; return v; }
    function fieldHtml(k, isMe) {
      var d = F[k]; var val = isMe ? (S.me[k] || '') : (P[k] || '');
      var big = /gap|praise|tip|c1|c2|c3|observation|compliment|opportunity/.test(k);
      if (k === 'bizword') return '<label class="pbt-f"><span>' + d[0] + '</span><select data-k="bizword">' + ['business', 'brand', 'page', 'client base', 'online presence'].map(function (o) { return '<option' + (o === val ? ' selected' : '') + '>' + o + '</option>'; }).join('') + '</select></label>';
      return '<label class="pbt-f' + (big ? ' wide' : '') + '"><span>' + d[0] + '</span>' + (big ? '<textarea rows="2" data-k="' + k + '" data-me="' + (isMe ? 1 : 0) + '" placeholder="e.g. ' + esc(d[1]) + '">' + esc(val) + '</textarea>' : '<input data-k="' + k + '" data-me="' + (isMe ? 1 : 0) + '" value="' + esc(val) + '" placeholder="e.g. ' + esc(d[1]) + '">') + '</label>';
    }
    function draw() {
      var meF = cur.f.filter(function (k) { return F[k][2]; }), prF = cur.f.filter(function (k) { return !F[k][2]; });
      var meDone = meF.every(function (k) { return clean(S.me[k]); });
      var usesGreet = cur.m.concat(cur.b || []).some(function (m) { return m[1].indexOf('{greet}') > -1; });
      var pick = '';
      if (keys.length > 1) {
        var groups = {}; keys.forEach(function (k) { var w = WK[k]; (groups[w.g] = groups[w.g] || []).push(w); });
        pick = '<div class="pbt-pick">' + (opts.embed ? '<span class="pbt-lab">Pick a version</span>' : '') + Object.keys(groups).map(function (g) {
          return (opts.embed ? '' : '<span class="pbt-lab">' + g + '</span>') + '<div class="pbt-chips">' + groups[g].map(function (w) { return '<button type="button" class="pbt-chip' + (w.k === cur.k ? ' on' : '') + '" data-pick="' + w.k + '">' + esc(opts.embed ? w.n.replace(/^.*· /, '') : w.n) + '</button>'; }).join('') + '</div>';
        }).join('') + '</div>';
      }
      root.innerHTML = pick +
        (lead ? '<div class="pbt-lead-note">Writing ' + esc(FU_NAME[lead.fu] || 'a follow-up') + ' for <b>@' + esc(lead.h) + '</b></div>' : '') +
        '<div class="pbt-grid2"><div class="pbt-form">' +
        (meF.length ? '<details class="pbt-me"' + (meDone ? '' : ' open') + '><summary><span>Your details</span><small>' + (meDone ? esc([S.me.name, S.me.role].filter(Boolean).join(' · ') || 'Saved') + ' · Edit' : 'Fill once, saved for every script') + '</small></summary><div class="pbt-fields">' + meF.map(function (k) { return fieldHtml(k, true); }).join('') + '</div></details>' : '') +
        '<div class="pbt-box"><div class="pbt-box-h">About this prospect</div><div class="pbt-fields">' +
        (usesGreet ? '<label class="pbt-f"><span>Greeting</span><select data-k="greet">' + ['Good morning', 'Good afternoon', 'Good evening', 'Hey', 'Hi'].map(function (o) { return '<option' + (o === P.greet ? ' selected' : '') + '>' + o + '</option>'; }).join('') + '</select></label>' : '') +
        prF.map(function (k) { return fieldHtml(k, false); }).join('') + '</div></div></div>' +
        '<div class="pbt-out"><div class="pbt-out-h"><b>Your ' + (cur.fu ? 'follow-up' : 'DMs') + ' · ' + esc(cur.n) + '</b><button type="button" class="pb-copy" data-copyall>Copy all</button></div><div id="pbtMsgs"></div>' +
        '<div class="pbt-acts">' + (lead ? '<button type="button" class="pb-btn" data-logfu>✓ I sent it · log follow-up</button>' : (cur.fu ? '' : '<button type="button" class="pb-btn" data-logsent>✓ I sent it · add to my tracker</button>')) +
        '<a class="pb-btn ghost" href="' + C.base + cur.l + '/">Read the script notes</a></div></div></div>';
      msgs(); bind();
    }
    function msgs() {
      var v = vals(), box = $('#pbtMsgs', root);
      box.innerHTML = cur.m.map(function (m, i) { return '<div class="pbt-msg"><div class="pbt-msg-h"><span class="n">' + (i + 1) + '</span>' + esc(m[0]) + '<button type="button" class="pb-copy" data-copy="' + i + '">Copy</button></div><div class="pbt-msg-b">' + fill(esc(m[1]).replace(/\n/g, '<br>'), v, true) + '</div></div>'; }).join('') +
        (cur.b ? '<div class="pbt-branch-h">When they reply</div>' + cur.b.map(function (m, i) { return '<div class="pbt-msg br"><div class="pbt-msg-h">' + esc(m[0]) + '<button type="button" class="pb-copy" data-copyb="' + i + '">Copy</button></div><div class="pbt-msg-b">' + fill(esc(m[1]).replace(/\n/g, '<br>'), v, true) + '</div></div>'; }).join('') : '');
    }
    function bind() {
      $$('[data-pick]', root).forEach(function (b) { b.onclick = function () { cur = WK[b.dataset.pick]; draw(); }; });
      $$('[data-k]', root).forEach(function (el) {
        el.addEventListener('input', function () { var k = el.dataset.k; if (el.dataset.me === '1') { S.me[k] = el.value; save(); } else P[k] = el.value; msgs(); });
        el.addEventListener('change', function () { var k = el.dataset.k; if (el.dataset.me === '1') { S.me[k] = el.value; save(); } else P[k] = el.value; msgs(); });
      });
      root.onclick = function (e) {
        var t = e.target.closest('button'); if (!t) return; var v = vals();
        if (t.hasAttribute('data-copy')) copy(fill(cur.m[+t.dataset.copy][1], v, false), t);
        else if (t.hasAttribute('data-copyb')) copy(fill(cur.b[+t.dataset.copyb][1], v, false), t);
        else if (t.hasAttribute('data-copyall')) copy(cur.m.map(function (m) { return fill(m[1], v, false); }).join('\n\n———\n\n'), t);
        else if (t.hasAttribute('data-logsent')) {
          var h = clean(P.handle); if (!h) { flash('Add their @handle first'); var hi = $('[data-k="handle"]', root); if (hi) hi.focus(); return; }
          addLead(h, P.platform || 'Instagram', cur.n, clean(P.gap || P.gap2 || P.compliment || ''));
          t.textContent = 'Added ✓ Follow-up due ' + nice(addDays(today(), 2)); t.disabled = true; flash('Added to your tracker');
        } else if (t.hasAttribute('data-logfu') && lead) { logFollowUp(lead); t.textContent = 'Logged ✓'; t.disabled = true; flash('Follow-up logged'); }
      };
    }
    draw();
  }

  /* ---------- tracker UI ---------- */
  function streak() { var n = 0, d = today(); if (!(S.days[d] && S.days[d].dm)) d = addDays(d, -1); while (S.days[d] && S.days[d].dm > 0) { n++; d = addDays(d, -1); } return n; }
  function sum(k, n) { var t = 0, d = today(); for (var i = 0; i < n; i++) { if (S.days[d]) t += S.days[d][k] || 0; d = addDays(d, -1); } return t; }
  var filter = 'all', q = '';
  function tracker(root) {
    var d = day(), circ = 2 * Math.PI * 34, pct = Math.min(1, d.dm / (S.goal || 1));
    var dm7 = sum('dm', 7), dm30 = sum('dm', 30), rep30 = sum('rep', 30), rate = dm30 ? Math.round(rep30 / dm30 * 100) : 0;
    var due = S.leads.filter(function (l) { return l.next && l.next <= today(); });
    var bars = [], max = 1; for (var i = 13; i >= 0; i--) { var dd = addDays(today(), -i), x = S.days[dd] || {}; bars.push([dd, x.dm || 0, x.fu || 0]); max = Math.max(max, (x.dm || 0) + (x.fu || 0), S.goal); }
    var CN = [['dm', 'DMs sent', '✉️'], ['fu', 'Follow-ups', '🔁'], ['rep', 'Replies', '💬'], ['call', 'Calls booked', '📞'], ['cl', 'Clients won', '🎉']];
    root.innerHTML =
      '<section class="pbt-today"><div class="pbt-today-l"><span class="pbt-k">Today · ' + new Date().toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'short' }) + '</span>' +
      '<h2>' + (d.dm >= S.goal ? 'Goal reached. Nice work.' : d.dm ? 'Keep going. ' + (S.goal - d.dm) + ' DMs to your goal.' : 'Send your first DM today.') + '</h2>' +
      '<div class="pbt-goal">Daily goal <button type="button" data-goal="-1">−</button><b>' + S.goal + '</b><button type="button" data-goal="1">+</button> DMs</div>' +
      '<div class="pbt-streak"><b>' + streak() + '-day</b> streak · ' + dm7 + ' DMs this week</div></div>' +
      '<div class="pbt-ringw"><svg viewBox="0 0 80 80" class="pbt-ring"><circle cx="40" cy="40" r="34"/><circle class="v" cx="40" cy="40" r="34" style="stroke-dasharray:' + circ.toFixed(1) + ';stroke-dashoffset:' + (circ * (1 - pct)).toFixed(1) + '"/></svg><div><b>' + d.dm + '</b><small>of ' + S.goal + '</small></div></div></section>' +
      '<div class="pbt-counters">' + CN.map(function (c) { return '<div class="pbt-c"><span class="e">' + c[2] + '</span><b>' + (d[c[0]] || 0) + '</b><small>' + c[1] + '</small><div class="pbt-pm"><button type="button" data-c="' + c[0] + '" data-v="-1" aria-label="Minus">−</button><button type="button" data-c="' + c[0] + '" data-v="1" aria-label="Plus">+</button></div></div>'; }).join('') + '</div>' +
      '<div class="pbt-two"><div class="pbt-card"><div class="pbt-card-h"><b>Last 14 days</b><small><i class="dm"></i>DMs <i class="fu"></i>Follow-ups</small></div><div class="pbt-bars">' + bars.map(function (b) { var h1 = b[1] / max * 100, h2 = b[2] / max * 100; return '<div class="pbt-bar' + (b[0] === today() ? ' t' : '') + '" title="' + nice(b[0]) + ': ' + b[1] + ' DMs, ' + b[2] + ' follow-ups"><div class="st"><i class="fu" style="height:' + h2 + '%"></i><i class="dm" style="height:' + h1 + '%"></i></div><small>' + b[0].slice(8) + '</small></div>'; }).join('') + '</div></div>' +
      '<div class="pbt-card"><div class="pbt-card-h"><b>Last 30 days</b></div><div class="pbt-kpis">' +
      '<div><b>' + dm30 + '</b><small>DMs sent</small></div><div><b>' + rate + '%</b><small>Reply rate <em>target 50–70%</em></small></div><div><b>' + sum('call', 30) + '</b><small>Calls booked</small></div><div><b>' + sum('cl', 30) + '</b><small>Clients won</small></div></div>' +
      (dm30 >= 20 && rate < 20 ? '<a class="pbt-tip" href="' + C.base + 'outreach-audit-diagnose-why-your-dms-aren-t-working/">Your reply rate is low. Run the Outreach Audit →</a>' : '<div class="pbt-tip ok">Tip: like or comment on 1–2 posts before you DM. Familiar names get replies.</div>') + '</div></div></div>' +
      '<div class="pbt-card"><div class="pbt-card-h"><b>Follow-ups due' + (due.length ? ' <span class="pbt-badge">' + due.length + '</span>' : '') + '</b><small>Day 2, 5, 7 and 30 after your first DM</small></div>' +
      (due.length ? due.map(function (l) { return '<div class="pbt-due"><span class="h">@' + esc(l.h) + '</span><span class="w">' + esc(FU_NAME[l.fu] || '') + (l.next < today() ? ' · <em>overdue</em>' : '') + '</span><a class="pb-btn" href="' + C.base + 'writer/?k=' + FU_KEY[l.fu] + 'a&lead=' + l.id + '">Write it</a><button type="button" class="pb-btn ghost" data-fu="' + l.id + '">Mark sent</button></div>'; }).join('') : '<p class="pbt-empty">Nothing due. Add prospects below as you DM them and I\'ll remind you when to follow up.</p>') + '</div>' +
      '<div class="pbt-card"><div class="pbt-card-h"><b>My prospects</b><small>' + S.leads.length + ' total</small></div>' +
      '<form class="pbt-add" id="pbtAdd"><input name="h" placeholder="@handle" required><select name="pl"><option>Instagram</option><option>WhatsApp</option><option>LinkedIn</option><option>X</option><option>TikTok</option><option>Email</option></select><select name="ang"><option value="">Script used</option>' + W.filter(function (w) { return !w.fu; }).map(function (w) { return '<option>' + w.n + '</option>'; }).join('') + '</select><button class="pb-btn">+ Add · counts as a DM</button></form>' +
      '<div class="pbt-filters"><input id="pbtQ" placeholder="Search prospects" value="' + esc(q) + '">' + [['all', 'All']].concat(STAGES.map(function (s) { return [s[0], s[1]]; })).map(function (s) { var n = s[0] === 'all' ? S.leads.length : S.leads.filter(function (l) { return l.st === s[0]; }).length; return '<button type="button" class="pbt-chip' + (filter === s[0] ? ' on' : '') + '" data-f="' + s[0] + '">' + s[1] + ' <small>' + n + '</small></button>'; }).join('') + '</div>' +
      '<div class="pbt-leads">' + leadsHtml() + '</div></div>';
    bindTracker(root);
  }
  function leadsHtml() {
    var list = S.leads.filter(function (l) { return (filter === 'all' || l.st === filter) && (!q || (l.h + ' ' + (l.notes || '')).toLowerCase().indexOf(q.toLowerCase()) > -1); });
    if (!list.length) return '<p class="pbt-empty">' + (S.leads.length ? 'No prospects match.' : 'No prospects yet. Use the DM Writer or add one above.') + '</p>';
    return list.slice(0, 200).map(function (l) {
      var s = STAGE[l.st] || STAGES[0];
      return '<div class="pbt-lead" data-id="' + l.id + '"><div class="pbt-lead-top"><span class="h">@' + esc(l.h) + '</span><span class="pl">' + esc(l.pl) + '</span>' +
        '<select data-st style="--sc:' + s[2] + '">' + STAGES.map(function (x) { return '<option value="' + x[0] + '"' + (x[0] === l.st ? ' selected' : '') + '>' + x[1] + '</option>'; }).join('') + '</select>' +
        '<button type="button" class="pbt-x" data-del aria-label="Delete">×</button></div>' +
        '<div class="pbt-lead-meta">First DM ' + nice(l.sent) + (l.ang ? ' · ' + esc(l.ang) : '') + (l.fu ? ' · ' + l.fu + ' follow-up' + (l.fu > 1 ? 's' : '') : '') + (l.next ? ' · <b>next ' + nice(l.next) + '</b>' : '') + '</div>' +
        '<textarea data-notes rows="1" placeholder="Notes: what they said, objections, what you offered">' + esc(l.notes || '') + '</textarea></div>';
    }).join('');
  }
  function bindTracker(root) {
    $$('[data-goal]', root).forEach(function (b) { b.onclick = function () { S.goal = Math.max(1, Math.min(200, S.goal + +b.dataset.goal)); save(); tracker(root); }; });
    $$('[data-c]', root).forEach(function (b) { b.onclick = function () { bump(b.dataset.c, +b.dataset.v); save(); tracker(root); }; });
    $$('[data-fu]', root).forEach(function (b) { b.onclick = function () { var l = S.leads.filter(function (x) { return x.id === b.dataset.fu; })[0]; if (l) { logFollowUp(l); tracker(root); flash('Follow-up logged'); } }; });
    $$('[data-f]', root).forEach(function (b) { b.onclick = function () { filter = b.dataset.f; tracker(root); }; });
    var qi = $('#pbtQ', root); qi.oninput = function () { q = qi.value; $('.pbt-leads', root).innerHTML = leadsHtml(); bindLeads(root); };
    $('#pbtAdd', root).onsubmit = function (e) { e.preventDefault(); var f = e.target; addLead(f.h.value, f.pl.value, f.ang.value, ''); flash('Prospect added · follow-up due ' + nice(addDays(today(), 2))); tracker(root); };
    bindLeads(root);
  }
  function bindLeads(root) {
    $$('.pbt-lead', root).forEach(function (row) {
      var l = S.leads.filter(function (x) { return x.id === row.dataset.id; })[0]; if (!l) return;
      $('[data-st]', row).onchange = function (e) { setStage(l, e.target.value); if (e.target.value === 'client') flash('New client! 🎉'); tracker(root); };
      $('[data-del]', row).onclick = function () { if (confirm('Delete @' + l.h + '?')) { S.leads = S.leads.filter(function (x) { return x !== l; }); save(); tracker(root); } };
      var n = $('[data-notes]', row); n.oninput = function () { l.notes = n.value; save(); };
    });
  }

  /* ---------- mount ---------- */
  var tr = document.getElementById('pbTracker'); if (tr) tracker(tr);
  var wr = document.getElementById('pbWriter');
  if (wr) { var sp = new URLSearchParams(location.search); var k = sp.get('k'); if (k && !WK[k] && WK[k + 'a']) k = k + 'a'; writer(wr, { k: k, leadId: sp.get('lead'), handle: sp.get('h') }); }
  var em = document.getElementById('pbWriterEmbed');
  if (em) { var ks = C.lessonToWriter[em.dataset.lesson]; if (ks) writer(em, { keys: ks, k: ks[0], embed: true }); else em.closest('.pbt-embed').remove(); }
  var mini = document.getElementById('pbTrkMini');
  if (mini) { var dd = day(), due = S.leads.filter(function (l) { return l.next && l.next <= today(); }).length; mini.innerHTML = '<b>' + dd.dm + ' of ' + S.goal + '</b> DMs sent today' + (streak() ? ' · ' + streak() + '-day streak' : '') + (due ? ' · <em>' + due + ' follow-up' + (due > 1 ? 's' : '') + ' due</em>' : ''); }
})();
