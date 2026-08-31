document.addEventListener('DOMContentLoaded',()=>{
  const toast=document.querySelector('.toast');
  if(toast)setTimeout(()=>toast.remove(),3500);

  const reducedMotion=window.matchMedia('(prefers-reduced-motion: reduce)');
  const formatMoney=cents=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(cents/100);

  document.querySelectorAll('.customize-trigger').forEach(trigger=>{
    const dialog=document.getElementById(trigger.dataset.dialog);
    if(!dialog)return;
    const form=dialog.querySelector('.customize-form');
    const quantityInput=form.querySelector('input[name="quantity"]');
    const quantityOutput=form.querySelector('.quantity-control output');
    const totalOutput=form.querySelector('.add-customized strong');
    const notes=form.querySelector('textarea[name="notes"]');
    let closingTimer;

    const updateTotal=()=>{
      const extras=[...form.querySelectorAll('input[name="addon_ids[]"]:checked')].reduce((sum,input)=>sum+Number(input.dataset.price||0),0);
      const quantity=Number(quantityInput.value||1);
      quantityOutput.textContent=String(quantity);
      totalOutput.textContent=formatMoney((Number(form.dataset.basePrice)+extras)*quantity);
    };

    const resetForm=()=>{
      form.reset();
      quantityInput.value='1';
      notes.parentElement.querySelector('small span').textContent='0';
      updateTotal();
    };

    const closeDialog=()=>{
      if(!dialog.open)return;
      dialog.classList.remove('is-open');
      dialog.classList.add('is-closing');
      clearTimeout(closingTimer);
      closingTimer=setTimeout(()=>{dialog.classList.remove('is-closing');dialog.close();trigger.focus();},reducedMotion.matches?0:160);
    };

    trigger.addEventListener('click',()=>{
      resetForm();
      dialog.showModal();
      requestAnimationFrame(()=>dialog.classList.add('is-open'));
    });
    dialog.querySelector('.dialog-close').addEventListener('click',closeDialog);
    dialog.addEventListener('cancel',event=>{event.preventDefault();closeDialog();});
    dialog.addEventListener('click',event=>{if(event.target===dialog)closeDialog();});
    form.querySelectorAll('fieldset').forEach(fieldset=>{
      fieldset.addEventListener('change',event=>{
        const checked=fieldset.querySelectorAll('input[type="checkbox"]:checked');
        if(checked.length>Number(fieldset.dataset.maxChoices||1))event.target.checked=false;
        updateTotal();
      });
    });
    form.querySelectorAll('[data-quantity]').forEach(button=>button.addEventListener('click',()=>{
      const delta=button.dataset.quantity==='plus'?1:-1;
      quantityInput.value=String(Math.max(1,Math.min(10,Number(quantityInput.value)+delta)));
      updateTotal();
    }));
    notes.addEventListener('input',()=>{notes.parentElement.querySelector('small span').textContent=String(notes.value.length);});
  });
});

(() => {
  const toggle = document.querySelector('.nav-toggle');
  const nav = document.getElementById('site-nav');
  if (!toggle || !nav) return;
  const close = () => {
    nav.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
  };
  toggle.addEventListener('click', () => {
    const open = nav.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(open));
  });
  document.addEventListener('click', event => {
    if (!nav.classList.contains('is-open')) return;
    if (nav.contains(event.target) || toggle.contains(event.target)) return;
    close();
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
  window.addEventListener('resize', () => { if (window.innerWidth > 680) close(); });
})();

(() => {
  const toggle = document.querySelector('.sidebar-toggle');
  const aside = document.getElementById('admin-sidebar');
  if (!toggle || !aside) return;
  const backdrop = document.createElement('div');
  backdrop.className = 'admin-backdrop';
  document.body.appendChild(backdrop);
  const close = () => {
    aside.classList.remove('is-open');
    backdrop.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
  };
  toggle.addEventListener('click', () => {
    const open = aside.classList.toggle('is-open');
    backdrop.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', String(open));
  });
  backdrop.addEventListener('click', close);
  document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
  window.addEventListener('resize', () => { if (window.innerWidth > 680) close(); });
})();

(() => {
  const topbar = document.querySelector('.topbar');
  if (!topbar || document.querySelector('.coupon-strip')) return;
  const strip = document.createElement('aside');
  strip.className = 'coupon-strip';
  strip.setAttribute('aria-label', 'Cupom de boas-vindas');
  strip.innerHTML = '<strong>15% OFF NO SEU PRIMEIRO PEDIDO</strong><button type="button"><b>BRASA15</b><span>Copiar cupom</span></button>';
  topbar.insertAdjacentElement('afterend', strip);
  const button = strip.querySelector('button');
  button.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText('BRASA15');
      button.querySelector('span').textContent = 'Cupom copiado!';
      window.setTimeout(() => button.querySelector('span').textContent = 'Copiar cupom', 1800);
    } catch (_) {
      button.querySelector('span').textContent = 'Use BRASA15';
    }
  });
})();

(() => {
  const board = document.querySelector('.kanban');
  if (!board) return;

  const columns = [...board.children];
  const statuses = ['received', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'];
  let draggedCard = null;
  let sourceIndex = -1;

  const announce = (message, type = 'success') => {
    document.querySelector('.kanban-notice')?.remove();
    const notice = document.createElement('div');
    notice.className = `kanban-notice ${type}`;
    notice.setAttribute('role', type === 'error' ? 'alert' : 'status');
    notice.textContent = message;
    document.body.appendChild(notice);
    window.setTimeout(() => notice.remove(), 2600);
  };

  columns.forEach((column, columnIndex) => {
    column.classList.add('kanban-column');
    column.dataset.status = statuses[columnIndex];

    column.addEventListener('dragover', event => {
      if (!draggedCard || columnIndex !== sourceIndex + 1) return;
      event.preventDefault();
      event.dataTransfer.dropEffect = 'move';
      column.classList.add('drop-ready');
    });
    column.addEventListener('dragleave', event => {
      if (!column.contains(event.relatedTarget)) column.classList.remove('drop-ready');
    });
    column.addEventListener('drop', async event => {
      event.preventDefault();
      column.classList.remove('drop-ready');
      if (!draggedCard || columnIndex !== sourceIndex + 1) return;

      const card = draggedCard;
      const form = card.querySelector('form');
      const select = form?.querySelector('select[name="status"]');
      if (!form || !select) return;
      select.value = statuses[columnIndex];
      card.classList.add('is-updating');

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          credentials: 'same-origin',
          headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        const result = await response.json().catch(() => null);
        if (!response.ok || !result?.ok || result.status !== statuses[columnIndex]) {
          throw new Error(result?.message || 'Falha ao atualizar o pedido');
        }
        column.appendChild(card);
        card.dataset.columnIndex = String(columnIndex);
        card.draggable = columnIndex < columns.length - 1;
        announce(`Pedido movido para ${column.querySelector('h2').textContent.trim()}.`);
      } catch (error) {
        select.value = statuses[sourceIndex];
        announce(error.message || 'Não foi possível mudar a etapa. Tente novamente.', 'error');
      } finally {
        card.classList.remove('is-updating');
        draggedCard = null;
        sourceIndex = -1;
      }
    });

    column.querySelectorAll(':scope > article').forEach(card => {
      card.dataset.columnIndex = String(columnIndex);
      card.draggable = columnIndex < columns.length - 1;
      card.setAttribute('aria-label', `${card.querySelector('b')?.textContent || 'Pedido'}. Arraste para a próxima etapa.`);
      card.addEventListener('dragstart', event => {
        sourceIndex = Number(card.dataset.columnIndex);
        if (sourceIndex >= columns.length - 1) return event.preventDefault();
        draggedCard = card;
        card.classList.add('is-dragging');
        columns[sourceIndex + 1]?.classList.add('is-next-stage');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', card.querySelector('b')?.textContent || 'pedido');
      });
      card.addEventListener('dragend', () => {
        card.classList.remove('is-dragging');
        columns.forEach(item => item.classList.remove('drop-ready', 'is-next-stage'));
        if (!card.classList.contains('is-updating')) {
          draggedCard = null;
          sourceIndex = -1;
        }
      });
    });
  });
})();

