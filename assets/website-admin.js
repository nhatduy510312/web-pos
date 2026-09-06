'use strict';
(() => {
  if (!window.fetch || !window.FormData || !window.AbortController) return;
  let saving = false;
  const signature = form => {
    const values = new FormData(form);
    values.delete('revision');
    return new URLSearchParams(values).toString();
  };
  document.addEventListener('submit', async event => {
    const form = event.target;
    // Serialize writes: all forms on this page share one optimistic-lock revision.
    if (saving) { event.preventDefault(); return; }
    if (!form.matches('.wa-products form[data-inline-save]')) return;
    event.preventDefault();
    const status = form.querySelector('[data-save-status]');
    const submitted = new FormData(form);
    const revision = submitted.get('revision');
    const submittedSignature = signature(form);
    const buttons = [...document.querySelectorAll('button')].filter(button => button.type === 'submit');
    const disabledStates = buttons.map(button => button.disabled);
    saving = true;
    buttons.forEach(button => { button.disabled = true; });
    form.setAttribute('aria-busy', 'true');
    status.className = 'wa-save-status';
    status.textContent = 'Đang lưu thông tin món…';
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 20000);
    try {
      // The hidden input named "action" shadows the form.action DOM property.
      const response = await fetch(form.getAttribute('action') || window.location.href, {
        method: 'POST', body: submitted, credentials: 'same-origin',
        headers: { Accept: 'application/json' }, signal: controller.signal,
      });
      if (response.redirected || !(response.headers.get('content-type') || '').includes('application/json')) {
        throw new Error(response.status === 403 || response.redirected
          ? 'Phiên đăng nhập có thể đã hết hạn. Nội dung bạn nhập vẫn được giữ; hãy đăng nhập lại ở tab khác rồi thử lưu.'
          : 'Chưa xác nhận được kết quả lưu. Nội dung bạn nhập vẫn được giữ; hãy kiểm tra kết quả ở tab khác trước khi lưu lại.');
      }
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.message || 'Không lưu được thông tin món.');
      if (!Number.isSafeInteger(result.revision) || result.revision !== Number(revision) + 1) {
        throw new Error('Chưa xác nhận được phiên bản đã lưu. Hãy kiểm tra kết quả ở tab khác trước khi lưu tiếp.');
      }
      // Keep unsaved values in other product cards; only advance their version tokens.
      document.querySelectorAll('input[name="revision"]').forEach(input => {
        if (input.value === revision) input.value = String(result.revision);
      });
      status.className = 'wa-save-status success';
      status.textContent = signature(form) === submittedSignature
        ? 'Đã lưu bản nháp của món. Bấm Xuất bản website khi bạn muốn áp dụng cho khách.'
        : 'Đã lưu bản vừa gửi. Bạn có thay đổi mới ở món này chưa được lưu.';
      const publishState = document.querySelector('.wa-publish strong');
      if (publishState) {
        // Keep the banner above the menu from shrinking and shifting the viewport.
        const summary = publishState.parentElement;
        summary.style.minHeight = Math.ceil(summary.getBoundingClientRect().height) + 'px';
        publishState.textContent = 'Có thay đổi chưa xuất bản';
      }
    } catch (error) {
      status.className = 'wa-save-status error';
      status.textContent = error instanceof TypeError || error.name === 'AbortError'
        ? 'Chưa xác nhận được kết quả lưu do kết nối gián đoạn. Nội dung bạn nhập vẫn được giữ; hãy kiểm tra ở tab khác trước khi lưu lại.'
        : error.message;
    } finally {
      clearTimeout(timeout);
      form.removeAttribute('aria-busy');
      buttons.forEach((button, index) => { button.disabled = disabledStates[index]; });
      saving = false;
    }
  });
})();
