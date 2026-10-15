(() => {
  const themeRoot = document.documentElement;
  let initialTheme = themeRoot.dataset.theme || 'dark';
  try { const saved = localStorage.getItem('portfolio-theme'); if (saved === 'dark' || saved === 'light') initialTheme = saved; } catch {}
  const nav = document.querySelector('header nav');
  const themeButton = document.createElement('button');
  themeButton.type = 'button'; themeButton.className = 'theme-toggle';
  const sun = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/></svg>';
  const moon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"/></svg>';
  function applyTheme(theme, persist = false) {
    const dark = theme !== 'light'; themeRoot.dataset.theme = dark ? 'dark' : 'light';
    themeButton.innerHTML = dark ? sun : moon;
    themeButton.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
    themeButton.title = dark ? 'Light mode' : 'Dark mode';
    themeButton.setAttribute('aria-pressed', String(!dark));
    if (persist) try { localStorage.setItem('portfolio-theme', themeRoot.dataset.theme); } catch {}
  }
  if (nav) {
    const actions = document.createElement('div'); actions.className = 'nav-theme-actions';
    const hire = nav.querySelector(':scope > div'); if (hire) actions.append(hire);
    actions.append(themeButton);
    const menu = nav.querySelector('button[aria-label="Menu"]'); if (menu) actions.append(menu);
    nav.append(actions);
  }
  applyTheme(initialTheme);
  themeButton.addEventListener('click', () => applyTheme(themeRoot.dataset.theme === 'dark' ? 'light' : 'dark', true));
  addEventListener('storage', event => { if (event.key === 'portfolio-theme') applyTheme(event.newValue === 'light' ? 'light' : 'dark'); });
  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const observer = new IntersectionObserver(entries => entries.forEach(({target, isIntersecting}) => {
    if (!isIntersecting) return;
    const transform = target.style.transform;
    target.style.opacity = '1';
    target.style.transform = 'none';
    if (!reduced) target.animate([{opacity:0,transform},{opacity:1,transform:'none'}],{duration:550,easing:'cubic-bezier(.2,.7,.3,1)',fill:'both'});
    observer.unobserve(target);
  }),{threshold:0.06});
  document.querySelectorAll('[style]').forEach(element => {
    if (element.style.opacity === '0') observer.observe(element);
  });
  const header = document.querySelector('header');
  const scroll = () => header?.classList.toggle('scrolled',scrollY > 20);
  addEventListener('scroll',scroll,{passive:true}); scroll();
  const menuButton = document.querySelector('button[aria-label="Menu"]');
  if (menuButton) {
    const menu = document.createElement('div');
    menu.className = 'mobile-navigation'; menu.id = 'mobile-navigation'; menu.hidden = true;
    document.querySelectorAll('header nav ul a').forEach(link => menu.append(link.cloneNode(true)));
    header.append(menu);
    menuButton.setAttribute('aria-controls',menu.id); menuButton.setAttribute('aria-expanded','false');
    const close = () => { menu.hidden = true; menuButton.setAttribute('aria-expanded','false'); };
    menuButton.addEventListener('click',() => { menu.hidden = !menu.hidden; menuButton.setAttribute('aria-expanded',String(!menu.hidden)); });
    addEventListener('keydown',event => {if(event.key === 'Escape') close();});
    addEventListener('resize',() => {if(innerWidth >= 768) close();});
    document.addEventListener('click',event => {if(!header.contains(event.target)) close();});
  }
  const form = document.querySelector('form');
  if (form) {
    ['name','email','message'].forEach(name => {form.elements[name].required = true;});
    form.elements.email.type = 'email';
    form.querySelectorAll('input:not([name="company"]),textarea').forEach(input => input.setAttribute('aria-label',input.placeholder));
    const status = document.createElement('p'); status.className = 'contact-status'; status.setAttribute('role','status'); form.append(status);
    form.addEventListener('submit',async event => {
      event.preventDefault();
      if (form.elements.company.value || !form.reportValidity()) return;
      const values = new FormData(form);
      if (window.portfolioConfig?.contactEndpoint) {
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;
        status.textContent = 'Sending your message…';
        try {
          const response = await fetch(window.portfolioConfig.contactEndpoint, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(Object.fromEntries(values))});
          const result = await response.json();
          if (!response.ok) throw new Error(result.error || 'Your message could not be saved. Please try again.');
          form.reset();
          status.textContent = 'Thank you! Your message has been received.';
        } catch (error) { status.textContent = error.message || 'Please try again or use the email link.'; }
        finally { submit.disabled = false; }
        return;
      }
      const subject = values.get('subject') || 'Portfolio project enquiry';
      const body = `${values.get('message')}\n\nFrom: ${values.get('name')}\nEmail: ${values.get('email')}`;
      const contactEmail = window.portfolioConfig?.email || document.querySelector('a[href^="mailto:"]')?.getAttribute('href').slice(7);
      if (!contactEmail) { status.textContent = 'Please use the contact details on this page.'; return; }
      location.href = `mailto:${contactEmail}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
      status.textContent = 'Your email app will open with your message. Send it there to complete your enquiry.';
    });
  }
})();
