// form-validation
document.addEventListener("DOMContentLoaded", function () {
    const password = document.getElementById("password");
    const confirm = document.getElementById("confirm_password");

    if (password && confirm) {
        const feedback = document.createElement("div");
        feedback.id = "passwordFeedback";
        feedback.style.marginTop = "5px";
        feedback.style.fontSize = "0.9em";
        confirm.parentNode.appendChild(feedback);

        function validatePasswords() {
            if (confirm.value.length === 0) {
                feedback.textContent = "";
                return;
            }
            if (password.value !== confirm.value) {
                feedback.textContent = "Les mots de passe ne correspondent pas";
                feedback.style.color = "red";
            } else {
                feedback.textContent = "Les mots de passe correspondent";
                feedback.style.color = "green";
            }
        }

        password.addEventListener("input", validatePasswords);
        confirm.addEventListener("input", validatePasswords);
    }
});

document.getElementById('checkAll').addEventListener('change', function () {
    let items = document.querySelectorAll('.checkItem');
    items.forEach(item => item.checked = this.checked);
});

// synchroniser : si un utilisateur décoche → décoche "Tout sélectionner"
document.querySelectorAll('.checkItem').forEach(item => {
    item.addEventListener('change', function () {
        if (!this.checked) {
            document.getElementById('checkAll').checked = false;
        } else {
            let allChecked = Array.from(document.querySelectorAll('.checkItem')).every(i => i.checked);
            document.getElementById('checkAll').checked = allChecked;
        }
    });
});