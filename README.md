# El Rápido — tienda online de maxikiosco

Sitio en PHP + MySQL con tienda, carrito, compras, cuentas de cliente, chatbot y panel de administración.

## Estructura
```
el/                    Código del sitio (esta carpeta es la que se publica)
├── admin/             Panel de administración (Config, Seccion, Template, assets)
├── assets/            CSS, JS e imágenes del sitio público
├── database/          el_rapido.sql (instalación) y generar_hash.php (crear/cambiar admin, solo consola)
├── includes/          Configuración, conexión, seguridad y funciones compartidas
├── storage/           Sesiones de respaldo (protegida por .htaccess)
└── *.php              Páginas públicas
docs/
├── auditorias/        Informes de los pasos 8 a 12
└── historial/         Notas de cambios de pasos anteriores
```

## Instalación
1. Requisitos: PHP 8+, MySQL 8.0.16+ o MariaDB 10.2+, Apache con `AllowOverride All` y `mod_rewrite`.
2. Importar `el/database/el_rapido.sql` (solo en base vacía). Crea la base `el_rapido`.
3. Copiar `el/includes/config.local.example.php` como `config.local.php` y completar: `ENV`, usuario/clave de MySQL (no `root`) y `SITE_URL` (`https://tu-dominio`).
4. Completar en `el/includes/config.php`: WhatsApp, teléfono, email y redes.
5. Cambiar la clave del admin de ejemplo (`admin@elrapido.com`) o crear uno propio:
   `php el/database/generar_hash.php correo "ClaveLarga12+" "Nombre"` y pegar el SQL en phpMyAdmin.
6. Ajustar los `ErrorDocument` de `el/.htaccess` si el sitio está en la raíz del dominio (`/404.php`, `/500.php`).
7. Cargar el catálogo real desde el panel (`/admin`) y borrar los productos de ejemplo.

Los íconos de las categorías son SVG (`iconoCategoria()` en `el/includes/iconos.php`), elegidos por el slug de cada categoría.

Modo oscuro: botón luna/sol en el header. Variables en `el/assets/css/global.css` (`--superficie`, `--texto`) y colores del tema en `el/assets/css/modo-oscuro.css`; la elección se guarda en el navegador (`localStorage.tema`).

Tokens de diseño (en `el/assets/css/global.css`, redefinidos en `modo-oscuro.css`): `--rojo-texto` (rojo para texto y enlaces, accesible en claro y oscuro; `--rojo` queda para fondos de botón), `--pad-pagina` (margen lateral común a todas las páginas), `--superficie-2`, `--ok-fondo`/`--ok-texto`, `--rojo-suave` y la escala `--fs-*`. Fotos del hero: `assets/img/hero-foto` (noche) y `hero-foto-2` (día), 1122×1402 (≈4:5), en `.webp` y `.jpg`.

Foto del negocio con fundido noche/día: `renderFotoHeroCiclo()` (en `el/includes/funciones.php`) + el ciclo en `el/assets/js/global.js` + `.foto-hero--ciclo` en `global.css`. Proporción fija 4:5, no se estira con el alto de la página. Se usa en Inicio, Login, Registro, Recuperar, Restablecer y Nosotros.
