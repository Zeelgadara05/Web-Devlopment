// Events page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    console.log("Events page loaded");
    
    // Add click animation to event cards
    const eventCards = document.querySelectorAll('.event-card');
    eventCards.forEach(card => {
        card.addEventListener('click', function() {
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = 'translateY(-5px)';
            }, 150);
        });
    });
});
