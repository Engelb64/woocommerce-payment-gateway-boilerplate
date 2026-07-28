# WooCommerce Payment Gateway Boilerplate

Boilerplate modular para crear plugins de pasarelas de pago y orquestadores en WooCommerce.

La **raíz de este repositorio es el plugin**. Docker monta este directorio en `wp-content/plugins/woocommerce-payment-gateway-boilerplate`, así que los cambios en código se reflejan al instante en WordPress.

## Documentación

| Archivo | Contenido |
|---|---|
| [README.md](./README.md) | Este archivo — inicio rápido y estado del proyecto |
| [DOCKER.md](./DOCKER.md) | Entorno local con Docker: setup, URLs, troubleshooting |

Más adelante (v1.0+), la documentación detallada vivirá en `documentation/` (arquitectura, nuevo provider, webhooks, blocks, etc.). El README seguirá siendo el punto de entrada.

## Requisitos

- Docker Desktop (o Docker + Docker Compose v2)
- Git

## Inicio rápido

```bash
git clone https://github.com/Engelb64/woocommerce-payment-gateway-boilerplate.git
cd woocommerce-payment-gateway-boilerplate

# Opcional: copiar variables de entorno
cp .env.example .env

docker compose up -d
```

Abrir [http://localhost:8080](http://localhost:8080).

Primera vez en WordPress:

1. Completar instalación (idioma, admin, etc.).
2. Instalar y activar **WooCommerce**.
3. Cuando exista el scaffold (v0.1+), activar **WooCommerce Payment Gateway Boilerplate** en Plugins.

Detalle del entorno: [DOCKER.md](./DOCKER.md).

## Estructura del repositorio

```text
woocommerce-payment-gateway-boilerplate/     ← raíz = plugin + docker
├── docker-compose.yml
├── .env.example
├── README.md
├── DOCKER.md
├── documentation/                           (v1.0+)
├── composer.json                            (v0.1+)
├── includes/                                (v0.1+)
├── assets/                                  (v0.6+)
├── tests/                                   (v0.7+)
└── woocommerce-payment-gateway-boilerplate.php (v0.1+)
```

No hay subcarpeta `plugins/` dentro del repo. El bind mount de Docker coloca este directorio en la ruta de plugins de WordPress.

## Estado del proyecto

| Versión | Estado |
|---|---|
| v0.0 | README + Docker scaffold |
| v0.1+ | Código del plugin (pendiente) |

## Licencia

[MIT](./LICENSE)
