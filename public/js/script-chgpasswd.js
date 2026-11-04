const passwordInput = document.getElementById('new_password');
const confirmInput = document.getElementById('confirm_password');
const matchIndicator = document.getElementById('password-match');
const rules = {
    length: value => [...value].length >= 8,
    lower: value => /\p{Ll}/u.test(value),
    upper: value => /\p{Lu}/u.test(value),
    digit: value => /\p{N}/u.test(value),
    special: value => /[^\p{L}\p{N}\s]/u.test(value)
};

function updatePasswordRequirements() {
    
    const value = passwordInput.value;
    
    document.querySelectorAll('#password-requirements li').forEach(item => {

        const ruleName = item.dataset.rule;
        const isValid = rules[ruleName](value);

        item.classList.toggle('met', isValid);
    });
}

function updatePasswordMatch() {
    
    const matches = 
        confirmInput.value !== '' &&
        passwordInput.value === confirmInput.value;

    matchIndicator.classList.toggle('met', matches);

    matchIndicator.textContent = matches
        ? '● Hasła są identyczne'
        : '● Hasła muszą być identyczne';
}

passwordInput.addEventListener('input', () => {
    updatePasswordRequirements();
    updatePasswordMatch();
});

confirmInput.addEventListener('input', updatePasswordMatch);

updatePasswordRequirements();
updatePasswordMatch();