(function () {
  'use strict';
  const productsNode = document.getElementById('bread-products');
  if (!productsNode) return;

  const products = JSON.parse(productsNode.textContent || '[]');
  const byId = new Map(products.map((product) => [String(product.id), product]));
  const storageKey = 'ghe-bread-cart-v1';
  const cartLines = document.getElementById('cart-lines');
  const cartCount = document.getElementById('cart-count');
  const cartTotal = document.getElementById('cart-total');
  const submitButton = document.getElementById('submit-order');
  const form = document.getElementById('preorder-form');
  const itemsInput = document.getElementById('items-json');
  const pickupDate = document.getElementById('pickup-date');
  const isEnglish = document.documentElement.lang === 'en';
  let cart = {};

  try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
    for (const [id, rawQuantity] of Object.entries(saved)) {
      if (!byId.has(id)) continue;
      const quantity = Number.parseInt(rawQuantity, 10);
      if (Number.isInteger(quantity) && quantity > 0) cart[id] = Math.min(quantity, 100);
    }
  } catch (_) {
    cart = {};
  }
  if (document.body.dataset.orderPlaced === '1') {
    cart = {};
    try { localStorage.removeItem(storageKey); } catch (_) {}
  }

  const formatMoney = (amount) => new Intl.NumberFormat(isEnglish ? 'en-US' : 'vi-VN').format(amount) + (isEnglish ? ' ₫' : 'đ');

  function setQuantity(id, quantity) {
    if (!byId.has(id)) return;
    if (quantity <= 0) delete cart[id];
    else cart[id] = Math.min(quantity, 100);
    try { localStorage.setItem(storageKey, JSON.stringify(cart)); } catch (_) {}
    render();
  }

  function render() {
    cartLines.replaceChildren();
    let itemCount = 0;
    let total = 0;
    const picked = [];

    for (const [id, quantity] of Object.entries(cart)) {
      const product = byId.get(id);
      if (!product || quantity < 1) continue;
      const lineTotal = product.price * quantity;
      itemCount += quantity;
      total += lineTotal;
      picked.push({ id: product.id, qty: quantity });

      const line = document.createElement('div');
      line.className = 'cart-line';
      const description = document.createElement('div');
      description.className = 'cart-line-description';
      const name = document.createElement('strong');
      name.textContent = product.name;
      const price = document.createElement('small');
      price.textContent = formatMoney(product.price) + (isEnglish ? ' / item' : ' / món');
      description.append(name, price);
      const controls = document.createElement('div');
      controls.className = 'cart-line-controls';
      const minus = document.createElement('button');
      minus.type = 'button';
      minus.textContent = '−';
      minus.setAttribute('aria-label', (isEnglish ? 'Decrease ' : 'Giảm ') + product.name);
      minus.addEventListener('click', () => setQuantity(id, quantity - 1));
      const count = document.createElement('span');
      count.textContent = String(quantity);
      const plus = document.createElement('button');
      plus.type = 'button';
      plus.textContent = '+';
      plus.setAttribute('aria-label', (isEnglish ? 'Increase ' : 'Tăng ') + product.name);
      plus.addEventListener('click', () => setQuantity(id, quantity + 1));
      controls.append(minus, count, plus);
      const subtotal = document.createElement('strong');
      subtotal.className = 'cart-line-total';
      subtotal.textContent = formatMoney(lineTotal);
      line.append(description, controls, subtotal);
      cartLines.append(line);
    }

    if (!itemCount) {
      const empty = document.createElement('p');
      empty.className = 'cart-empty';
      empty.textContent = isEnglish ? 'Your cart is empty. Choose a product to get started.' : 'Giỏ hàng đang trống. Chọn bánh bạn thích để bắt đầu nhé.';
      cartLines.append(empty);
    }

    cartCount.textContent = itemCount + (isEnglish ? (itemCount === 1 ? ' item' : ' items') : ' món');
    cartTotal.textContent = formatMoney(total);
    const hasPreorderProduct = Object.keys(cart).some((id) => (byId.get(id)?.lead_days || 1) >= 3);
    pickupDate.min = hasPreorderProduct ? pickupDate.dataset.minPreorder : pickupDate.dataset.minDaily;
    if (pickupDate.value && pickupDate.value < pickupDate.min) pickupDate.value = pickupDate.min;
    submitButton.disabled = itemCount === 0;
    itemsInput.value = JSON.stringify(picked);

    document.querySelectorAll('[data-product-id]').forEach((card) => {
      const id = card.getAttribute('data-product-id');
      const quantity = cart[id] || 0;
      const value = card.querySelector('[data-quantity-value]');
      value.textContent = String(quantity);
    });
  }

  document.querySelectorAll('[data-quantity-for]').forEach((control) => {
    control.addEventListener('click', (event) => {
      const button = event.target.closest('[data-quantity-change]');
      if (!button) return;
      const id = control.dataset.quantityFor;
      setQuantity(id, (cart[id] || 0) + Number(button.dataset.quantityChange));
    });
  });

  const deliveryWrap = document.getElementById('delivery-address-wrap');
  const deliveryAddress = document.getElementById('delivery-address');
  document.querySelectorAll('input[name="fulfillment_method"]').forEach((radio) => {
    radio.addEventListener('change', () => {
      const isDelivery = document.querySelector('input[name="fulfillment_method"]:checked').value === 'delivery';
      deliveryWrap.hidden = !isDelivery;
      deliveryAddress.required = isDelivery;
    });
  });

  form.addEventListener('submit', (event) => {
    if (!Object.keys(cart).length) {
      event.preventDefault();
      return;
    }
    submitButton.disabled = true;
    submitButton.textContent = isEnglish ? 'Submitting…' : 'Đang gửi đơn…';
  });
  render();
}());
