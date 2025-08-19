document.addEventListener("DOMContentLoaded", function() {
    const radioButtons = document.querySelectorAll('input[name="ai_bot_forum_restriction"]');
    const checkboxes = document.querySelectorAll('.ai-bot-forum-checkbox');
    const selectionDiv = document.getElementById('ai-bot-forum-selection');
    
    function updateCheckboxState() {
        const isSelected = document.querySelector('input[name="ai_bot_forum_restriction"]:checked').value === "selected";
        checkboxes.forEach(checkbox => checkbox.disabled = !isSelected);
        selectionDiv.style.opacity = isSelected ? "1" : "0.5";
    }
    
    radioButtons.forEach(radio => radio.addEventListener("change", updateCheckboxState));
    updateCheckboxState();
});