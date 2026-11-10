# Proyectos Portfolio

Plugin de WordPress para mostrar proyectos en tarjetas.

## Qué hace

- **Custom Post Type `proyecto`**: añade el menú "Proyectos" en el admin, con imagen destacada, extracto y dos campos propios (enlace y tecnologías).
- **Shortcode `[proyectos]`**: muestra los proyectos en una rejilla responsive. Acepta `numero`, por ejemplo `[proyectos numero="3"]`.
- **Página de ajustes** (Ajustes > Proyectos Portfolio): número de proyectos, mostrar u ocultar las tecnologías y texto del botón.

## Seguridad

- Nonce y comprobación de permisos al guardar los campos.
- Todo lo que entra se sanea (`esc_url_raw`, `sanitize_text_field`, `absint`) y todo lo que sale se escapa (`esc_html`, `esc_attr`, `esc_url`).
- Ajustes guardados con la Settings API de WordPress.

## Instalación

1. Sube la carpeta `proyectos-portfolio` a `wp-content/plugins/` (o el .zip desde Plugins > Añadir nuevo > Subir plugin).
2. Actívalo en Plugins.
3. Crea proyectos en el menú "Proyectos".
4. Pega `[proyectos]` en cualquier página.

Requiere WordPress 6.0+ y PHP 7.4+.
