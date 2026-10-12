'use strict';
document.querySelectorAll('form[data-confirm]').forEach(form => {
  form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
});
const booking = document.querySelector('[data-booking-price]');
if (booking) {
  const guests = booking.querySelector('[name="travellers"]');
  const total = document.getElementById('booking-total');
  const update = () => {
    const amount = Number(booking.dataset.bookingPrice) * Number(guests.value) / 100;
    total.textContent = Number.isFinite(amount) && amount > 0 ? 'LKR ' + new Intl.NumberFormat('en-LK', {minimumFractionDigits: amount % 1 ? 2 : 0, maximumFractionDigits: 2}).format(amount) : '—';
  };
  guests.addEventListener('input', update);
  update();
}
