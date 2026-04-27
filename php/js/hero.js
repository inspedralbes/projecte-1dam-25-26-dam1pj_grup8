document.addEventListener("DOMContentLoaded", function () {

    // Botón de "Entrar"
    const enterBtn = document.getElementById("enterBtn");

    // Contenedor de los roles (admin,profe y tecnic)
    const roles = document.getElementById("roles");

    // Para cuando haces click en entrar: 
    enterBtn.addEventListener("click", function (e) {

        // Evita comportamiento por defecto (por ejemplo, link)
        e.preventDefault();

        // eliminar boton entrar
        enterBtn.remove();

        // Muestra el bloque de roles
        roles.classList.remove("d-none");

        // Añadir estilos
        roles.classList.add("hero-roles", "mt-3");
    });

});