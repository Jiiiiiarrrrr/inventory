/**
 * Swal Compatibility Layer
 * Replaces SweetAlert2 CDN with a local implementation
 * Uses the same API as SweetAlert2: Swal.fire({...})
 * Returns Promise<{isConfirmed, value}> like real SweetAlert2
 */
(function() {
  // Create modal overlay
  const overlay = document.createElement('div');
  overlay.id = 'swal-overlay';
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:10000;padding:20px;';
  document.body.appendChild(overlay);

  // Create toast container
  const toastContainer = document.createElement('div');
  toastContainer.id = 'swal-toast-container';
  toastContainer.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:10001;display:flex;flex-direction:column;gap:10px;pointer-events:none;';
  document.body.appendChild(toastContainer);

  const ICONS = {
    success: '<svg viewBox="0 0 24 24" style="width:48px;height:48px;stroke:#27ae60;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M20 6L9 17l-5-5"/></svg>',
    error:   '<svg viewBox="0 0 24 24" style="width:48px;height:48px;stroke:#c0392b;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>',
    warning: '<svg viewBox="0 0 24 24" style="width:48px;height:48px;stroke:#e67e22;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>',
    info:    '<svg viewBox="0 0 24 24" style="width:48px;height:48px;stroke:#3498db;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
  };

  function createModal(options) {
    return new Promise((resolve) => {
      const {
        title = '',
        text = '',
        html = '',
        icon = 'info',
        showCancelButton = false,
        showDenyButton = false,
        confirmButtonText = 'OK',
        cancelButtonText = 'Cancel',
        denyButtonText = 'No',
        confirmButtonColor = '#a9714a',
        cancelButtonColor = '#7A5C3A',
        denyButtonColor = '#7A5C3A',
        input = null,
        inputPlaceholder = '',
        inputValidator = null,
        inputOptions = null,
        timer = 0,
        showConfirmButton = true,
        timerProgressBar = false,
        allowOutsideClick = true,
        stackButtons = false,
      } = options;

      // Build modal HTML
      let iconHtml = ICONS[icon] ? `<div style="margin-bottom:16px">${ICONS[icon]}</div>` : '';
      let inputHtml = '';

      if (input === 'text' || input === 'number' || input === 'password') {
        inputHtml = `<input id="swal-input" type="${input}" placeholder="${inputPlaceholder}" style="width:100%;padding:10px 14px;border:1.5px solid #D4BC8A;border-radius:10px;font-size:0.95rem;outline:none;margin:12px 0;background:#fff;color:#2C1A0E" />`;
      } else if (input === 'select' && inputOptions) {
        let opts = '<option value="">-- Select --</option>';
        for (const [val, label] of Object.entries(inputOptions)) {
          opts += `<option value="${val}">${label}</option>`;
        }
        inputHtml = `<select id="swal-input" style="width:100%;padding:10px 14px;border:1.5px solid #D4BC8A;border-radius:10px;font-size:0.95rem;outline:none;margin:12px 0;background:#fff;color:#2C1A0E">${opts}</select>`;
      }

      const modal = document.createElement('div');
      modal.style.cssText = 'background:#fff;border-radius:20px;padding:32px;max-width:440px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3);text-align:center;animation:swalpop .2s ease;';
      modal.innerHTML = `
        ${iconHtml}
        ${title ? `<h3 style="margin:0 0 8px;font-size:1.3rem;color:#2C1A0E">${title}</h3>` : ''}
        ${text ? `<p style="margin:0 0 12px;color:#7A5C3A;font-size:0.95rem">${text}</p>` : ''}
        ${html || ''}
        ${inputHtml}
        ${stackButtons ? `
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:20px;align-items:stretch">
          <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            ${showDenyButton ? `<button id="swal-deny" style="flex:1;padding:10px 24px;border-radius:10px;border:none;background:${denyButtonColor};color:#fff;font-weight:700;cursor:pointer;font-size:0.95rem">${denyButtonText}</button>` : ''}
            ${showConfirmButton ? `<button id="swal-confirm" style="flex:1;padding:10px 24px;border-radius:10px;border:none;background:${confirmButtonColor};color:#fff;font-weight:700;cursor:pointer;font-size:0.95rem">${confirmButtonText}</button>` : ''}
          </div>
          ${showCancelButton ? `<button id="swal-cancel" style="padding:10px 24px;border-radius:10px;border:none;background:${cancelButtonColor};color:#fff;font-weight:700;cursor:pointer;font-size:0.95rem">${cancelButtonText}</button>` : ''}
        </div>
        ` : `
        <div style="display:flex;gap:10px;margin-top:20px;justify-content:center;flex-wrap:wrap">
          ${showCancelButton ? `<button id="swal-cancel" style="padding:10px 24px;border-radius:10px;border:none;background:${cancelButtonColor};color:#fff;font-weight:700;cursor:pointer;font-size:0.95rem">${cancelButtonText}</button>` : ''}
          ${showDenyButton ? `<button id="swal-deny" style="padding:10px 24px;border-radius:10px;border:none;background:${denyButtonColor};color:#fff;font-weight:700;cursor:pointer;font-size:0.95rem">${denyButtonText}</button>` : ''}
          ${showConfirmButton ? `<button id="swal-confirm" style="padding:10px 24px;border-radius:10px;border:none;background:${confirmButtonColor};color:#fff;font-weight:700;cursor:pointer;font-size:0.95rem">${confirmButtonText}</button>` : ''}
        </div>
        `}
        ${timerProgressBar && timer ? `<div style="height:4px;background:#eee;border-radius:2px;margin-top:16px;overflow:hidden"><div id="swal-timer-bar" style="height:100%;background:${confirmButtonColor};width:100%;transition:width ${timer}ms linear"></div></div>` : ''}
      `;

      overlay.innerHTML = '';
      overlay.appendChild(modal);
      overlay.style.display = 'flex';

      // Auto close on timer
      let timerId = null;
      if (timer > 0) {
        timerId = setTimeout(() => {
          closeModal();
          resolve({ isConfirmed: false, isDenied: false, isDismissed: true, value: null });
        }, timer);
        // Animate timer bar
        setTimeout(() => {
          const bar = document.getElementById('swal-timer-bar');
          if (bar) bar.style.width = '0%';
        }, 50);
      }

      // Close on overlay click
      overlay.onclick = (e) => {
        if (e.target === overlay && allowOutsideClick) {
          clearTimeout(timerId);
          closeModal();
          resolve({ isConfirmed: false, isDenied: false, isDismissed: true, value: null });
        }
      };

      function closeModal() {
        clearTimeout(timerId);
        overlay.style.display = 'none';
        overlay.innerHTML = '';
      }

      // Cancel button
      const cancelBtn = document.getElementById('swal-cancel');
      if (cancelBtn) {
        cancelBtn.onclick = () => {
          clearTimeout(timerId);
          closeModal();
          resolve({ isConfirmed: false, isDenied: false, isDismissed: true, value: null });
        };
      }

      // Deny button
      const denyBtn = document.getElementById('swal-deny');
      if (denyBtn) {
        denyBtn.onclick = () => {
          clearTimeout(timerId);
          closeModal();
          resolve({ isConfirmed: false, isDenied: true, isDismissed: false, value: null });
        };
      }

      // Confirm button
      const confirmBtn = document.getElementById('swal-confirm');
      if (confirmBtn) {
        confirmBtn.onclick = async () => {
          const inputEl = document.getElementById('swal-input');
          let value = inputEl ? inputEl.value : null;

          // Run input validator
          if (inputValidator && inputEl) {
            const error = await inputValidator(value);
            if (error) {
              inputEl.style.borderColor = '#c0392b';
              inputEl.setCustomValidity(error);
              inputEl.reportValidity();
              return;
            }
          }

          clearTimeout(timerId);
          closeModal();
          resolve({ isConfirmed: true, isDenied: false, isDismissed: false, value: value });
        };
      }

      // Enter key submits
      if (input) {
        const inputEl = document.getElementById('swal-input');
        if (inputEl) {
          inputEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && confirmBtn) confirmBtn.click();
          });
          setTimeout(() => inputEl.focus(), 100);
        }
      }
    });
  }

  // Global Swal object
  window.Swal = {
    fire: (options) => {
      if (options.toast) {
        // Toast notification
        return new Promise((resolve) => {
          const toast = document.createElement('div');
          const iconColor = options.icon === 'success' ? '#27ae60' : options.icon === 'warning' ? '#e67e22' : options.icon === 'error' ? '#c0392b' : '#3498db';
          const iconSymbol = options.icon === 'success' ? '✓' : options.icon === 'warning' ? '' : options.icon === 'error' ? '' : 'ℹ';
          toast.style.cssText = `background:#fff;border-radius:12px;padding:12px 20px;box-shadow:0 8px 24px rgba(0,0,0,.15);font-size:0.9rem;color:#2C1A0E;display:flex;align-items:center;gap:10px;pointer-events:auto;animation:toastslide .3s ease;border-left:4px solid ${iconColor};`;
          toast.innerHTML = `<span style="color:${iconColor};font-weight:700;font-size:1.1rem">${iconSymbol}</span><span>${options.title || ''}</span>`;
          toastContainer.appendChild(toast);

          setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity .3s';
            setTimeout(() => toast.remove(), 300);
          }, options.timer || 2000);

          resolve({ isConfirmed: true });
        });
      }

      // Modal
      return createModal(options);
    },

    update: (newOptions) => {
      // Update modal content (for loading states)
      const modal = overlay.querySelector('div');
      if (!modal) return;
      if (newOptions.html) {
        const htmlEl = modal.querySelector('div') || modal;
        // Append or update html content
      }
    },

    close: () => {
      overlay.style.display = 'none';
      overlay.innerHTML = '';
    },
  };

  // Add animations
  const style = document.createElement('style');
  style.textContent = `
    @keyframes swalpop { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    @keyframes toastslide { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
  `;
  document.head.appendChild(style);
})();