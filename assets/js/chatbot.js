/**
 * AJ Wills & Estate Planning — "William" Chatbot
 * Friendly, warm, reassuring lead-qualification assistant.
 * Does NOT provide legal advice — encourages consultation bookings.
 * Captures: service required, best time to contact, name, email, phone, consent.
 * Sends lead to php/chatbot-lead.php via fetch().
 */

(function () {
  'use strict';

  const state = {
    step: 0,
    steps: [],
    leadData: {},
    consentChecked: false,
  };

  const SERVICE_LABELS = {
    'Will Writing': 'Will Writing',
    'Mirror Wills': 'Mirror Wills',
    'Lasting Powers of Attorney': 'Lasting Powers of Attorney',
    'Will Storage': 'Will Storage',
    'General Estate Planning': 'General Estate Planning',
    'Not Sure Yet': 'Not Sure Yet',
    'Speak to AJ Wills': 'General Enquiry — Speak to AJ Wills',
  };

  const QUALIFICATION_STEP = {
    id: 'service',
    botMessage: "No problem — I'm not able to give legal advice, but I can point you in the right direction and get one of the team at AJ Wills to call you back. Which of these best describes what you need help with?",
    options: [
      { label: 'Will Writing', value: 'Will Writing' },
      { label: 'Mirror Wills', value: 'Mirror Wills' },
      { label: 'Lasting Powers of Attorney', value: 'Lasting Powers of Attorney' },
      { label: 'Will Storage', value: 'Will Storage' },
      { label: 'General Estate Planning', value: 'General Estate Planning' },
      { label: "Not sure yet", value: 'Not Sure Yet' },
    ],
    stateKey: 'service',
  };

  const REST_STEPS = [
    {
      id: 'bestTime',
      botMessage: 'Thanks! And what\'s the best time of day for the team to give you a call?',
      options: [
        { label: 'Morning', value: 'Morning' },
        { label: 'Afternoon', value: 'Afternoon' },
        { label: 'Evening', value: 'Evening' },
        { label: 'Anytime', value: 'Anytime' },
      ],
      stateKey: 'bestTime',
    },
    {
      id: 'name',
      botMessage: "Great — let's get a callback booked in. What's your first name?",
      inputMode: true,
      inputPlaceholder: 'Your first name...',
      stateKey: 'name',
    },
    {
      id: 'email',
      botMessage: "Thanks, <strong>{{name}}</strong>! What's your email address?",
      inputMode: true,
      inputPlaceholder: 'Your email address...',
      stateKey: 'email',
      validate: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? null : "That doesn't look quite right — could you double-check your email address?",
    },
    {
      id: 'phone',
      botMessage: "And the best phone number to reach you on?",
      inputMode: true,
      inputPlaceholder: 'Your phone number...',
      stateKey: 'phone',
    },
    {
      id: 'consent',
      botMessage: "Almost there! Before I pass your details to the team, please confirm you're happy for AJ Wills & Estate Planning to contact you about your enquiry.",
      consentMode: true,
    },
    { id: 'complete', botMessage: null },
  ];

  let toggleBtn, panel, messagesEl, optionsEl, inputEl, sendBtn, widget;
  let proactive, proactiveClose;

  function init() {
    widget     = document.getElementById('chatbotWidget');
    toggleBtn  = document.getElementById('chatbotToggle');
    panel      = document.getElementById('chatbotPanel');
    messagesEl = document.getElementById('chatMessages');
    optionsEl  = document.getElementById('chatOptions');
    inputEl    = document.getElementById('chatInput');
    sendBtn    = document.getElementById('chatSend');
    proactive  = document.getElementById('chatbotProactive');
    proactiveClose = document.getElementById('chatbotProactiveClose');

    if (!widget || !toggleBtn || !panel) return;

    toggleBtn.addEventListener('click', togglePanel);
    sendBtn.addEventListener('click', handleSend);
    inputEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') handleSend(); });

    // Proactive greeting after 20 seconds, per spec
    setTimeout(() => {
      if (!panel.classList.contains('open') && !sessionStorage.getItem('ajAssistant_dismissed')) {
        proactive?.classList.add('show');
      }
    }, 20000);

    proactiveClose?.addEventListener('click', () => {
      proactive.classList.remove('show');
      sessionStorage.setItem('ajAssistant_dismissed', '1');
    });

    document.querySelectorAll('[data-chat-quickstart]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const value = btn.dataset.chatQuickstart;
        proactive?.classList.remove('show');
        sessionStorage.setItem('ajAssistant_dismissed', '1');
        openPanel();
        startConversation(value === 'speak' ? 'Speak to AJ Wills' : value);
      });
    });
  }

  function togglePanel() {
    const isOpen = panel.classList.contains('open');
    if (isOpen) {
      panel.classList.remove('open');
      toggleBtn.setAttribute('aria-expanded', 'false');
    } else {
      openPanel();
      if (messagesEl.children.length === 0) startConversation();
      inputEl.focus();
    }
  }

  function openPanel() {
    proactive?.classList.remove('show');
    panel.classList.add('open');
    toggleBtn.setAttribute('aria-expanded', 'true');
    sessionStorage.setItem('ajAssistant_dismissed', '1');
  }

  // ── Conversation ───────────────────────────────────────────────────────
  function startConversation(presetService) {
    state.step = 0;
    state.leadData = {};

    if (presetService) {
      state.leadData.service = SERVICE_LABELS[presetService] || presetService;
      state.steps = REST_STEPS;
      const greeting = `Hi, I'm <strong>William</strong> 👋. Thanks for letting me know you're interested in <strong>${state.leadData.service}</strong> — I can't give legal advice myself, but I can get the right person at AJ Wills to call you back. Let's grab a few quick details.`;
      showTypingThenMessage(greeting, () => advanceStep(true));
    } else {
      state.steps = [QUALIFICATION_STEP, ...REST_STEPS];
      const greeting = "Hi there, I'm <strong>William</strong> 👋 — here to help point you in the right direction and, if you'd like, arrange for AJ Wills to give you a call. What can I help with today?";
      showTypingThenMessage(greeting, () => showOptions(state.steps[0].options));
    }
  }

  function showTypingThenMessage(message, callback, delay = 800) {
    const typing = addTypingIndicator();
    setTimeout(() => {
      typing.remove();
      addBotMessage(message);
      if (callback) callback();
    }, delay);
  }

  function addBotMessage(html) {
    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble bot';
    bubble.innerHTML = interpolate(html, state.leadData);
    messagesEl.appendChild(bubble);
    scrollToBottom();
    return bubble;
  }

  function addUserMessage(text) {
    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble user';
    bubble.textContent = text;
    messagesEl.appendChild(bubble);
    scrollToBottom();
  }

  function addTypingIndicator() {
    const el = document.createElement('div');
    el.className = 'chatbot-typing';
    el.setAttribute('aria-label', 'William is typing');
    el.innerHTML = '<div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>';
    messagesEl.appendChild(el);
    scrollToBottom();
    return el;
  }

  function showOptions(options) {
    optionsEl.innerHTML = '';
    options.forEach((opt) => {
      const btn = document.createElement('button');
      btn.className = 'chat-option-btn';
      btn.textContent = opt.label;
      btn.setAttribute('aria-label', 'Select: ' + opt.label);
      btn.addEventListener('click', () => handleOptionSelect(opt.value, opt.label));
      optionsEl.appendChild(btn);
    });
  }

  function showConsentControl() {
    optionsEl.innerHTML = '';
    const wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;flex-direction:column;gap:8px;width:100%;';

    const label = document.createElement('label');
    label.className = 'form-check';
    label.style.cssText = 'background:var(--stone-200);padding:10px 12px;border-radius:10px;';
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.id = 'chatConsentCheckbox';
    const span = document.createElement('span');
    span.textContent = 'I consent to AJ Wills & Estate Planning contacting me about my enquiry.';
    label.appendChild(checkbox);
    label.appendChild(span);

    const continueBtn = document.createElement('button');
    continueBtn.className = 'chat-option-btn';
    continueBtn.textContent = 'Continue';
    continueBtn.disabled = true;
    continueBtn.style.opacity = '0.5';

    checkbox.addEventListener('change', () => {
      continueBtn.disabled = !checkbox.checked;
      continueBtn.style.opacity = checkbox.checked ? '1' : '0.5';
    });

    continueBtn.addEventListener('click', () => {
      if (!checkbox.checked) return;
      state.leadData.consent = 'Yes';
      addUserMessage('I consent to being contacted.');
      clearOptions();
      advanceStep();
    });

    wrap.appendChild(label);
    wrap.appendChild(continueBtn);
    optionsEl.appendChild(wrap);
  }

  function clearOptions() { optionsEl.innerHTML = ''; }

  function handleOptionSelect(value, label) {
    const currentStep = state.steps[state.step];
    state.leadData[currentStep.stateKey] = value;
    addUserMessage(label);
    clearOptions();
    advanceStep();
  }

  function handleSend() {
    const value = inputEl.value.trim();
    if (!value) return;
    const currentStep = state.steps[state.step];
    if (!currentStep || !currentStep.inputMode) return;

    if (currentStep.validate) {
      const error = currentStep.validate(value);
      if (error) {
        inputEl.value = '';
        showTypingThenMessage(error, null, 400);
        return;
      }
    }

    addUserMessage(value);
    state.leadData[currentStep.stateKey] = value;
    inputEl.value = '';
    inputEl.placeholder = 'Type a message...';
    advanceStep();
  }

  function advanceStep(isFirstOfPreset) {
    if (!isFirstOfPreset) state.step++;
    const nextStep = state.steps[state.step];
    if (!nextStep) return;

    if (nextStep.id === 'complete') { submitLead(); return; }

    const delay = isFirstOfPreset ? 200 : 700;
    showTypingThenMessage(nextStep.botMessage, () => {
      if (nextStep.consentMode) {
        showConsentControl();
      } else if (nextStep.inputMode) {
        inputEl.placeholder = nextStep.inputPlaceholder || 'Type here...';
        inputEl.focus();
      } else if (nextStep.options) {
        showOptions(nextStep.options);
      }
    }, delay);
  }

  // ── Lead Submission ────────────────────────────────────────────────────
  function submitLead() {
    showTypingThenMessage('Sending your details to the AJ Wills team...', null, 300);

    const body = new URLSearchParams({
      name:      state.leadData.name || '',
      email:     state.leadData.email || '',
      phone:     state.leadData.phone || '',
      service:   state.leadData.service || '',
      best_time: state.leadData.bestTime || '',
      consent:   state.leadData.consent || '',
      source:    'chatbot',
    });

    fetch('/php/chatbot-lead.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    })
      .then((res) => res.json())
      .then(() => {
        const successMsg = `
          ✅ <strong>All done, ${state.leadData.name || 'there'}!</strong><br><br>
          Thank you — a member of the AJ Wills team will call you ${state.leadData.bestTime ? '(' + state.leadData.bestTime.toLowerCase() + ')' : ''} at <strong>${state.leadData.phone || 'the number you provided'}</strong>.<br><br>
          In the meantime, you're welcome to <a href="/get-in-touch/" style="color:var(--terracotta-600);font-weight:600">book a consultation</a> directly, or browse our <a href="/guides/" style="color:var(--terracotta-600);font-weight:600">free guides</a>.
        `;
        addBotMessage(successMsg);
        clearOptions();
        inputEl.disabled = true;
        sendBtn.disabled = true;
        inputEl.placeholder = 'Enquiry submitted — thank you!';
      })
      .catch(() => {
        addBotMessage(
          "I wasn't able to send your details just now. Please <a href='/get-in-touch/' style='color:var(--terracotta-600);font-weight:600'>get in touch here</a> and the team will help right away — sorry for the inconvenience!"
        );
      });
  }

  // ── Utilities ──────────────────────────────────────────────────────────
  function interpolate(template, data) {
    return template.replace(/\{\{(\w+)\}\}/g, (_, key) => data[key] || '');
  }
  function scrollToBottom() { messagesEl.scrollTop = messagesEl.scrollHeight; }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
