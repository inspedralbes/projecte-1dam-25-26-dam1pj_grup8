document.addEventListener("DOMContentLoaded", function () {

    const enterBtn = document.getElementById("enterBtn");
    const roles = document.getElementById("roles");

    enterBtn.addEventListener("click", function (e) {
        e.preventDefault();

        // Oculta botón entrar
        enterBtn.remove();

        // Muestra roles
        roles.classList.remove("d-none");
        roles.classList.add("hero-roles", "mt-3");
    });

});