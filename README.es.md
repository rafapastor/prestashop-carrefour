# Conector Carrefour Marketplace para PrestaShop

[![Licencia: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](LICENSE)
[![PrestaShop](https://img.shields.io/badge/PrestaShop-1.6%20%E2%86%92%209.x-pink.svg)](https://www.prestashop.com)
[![PHP](https://img.shields.io/badge/PHP-7.1%2B-777bb4.svg)](https://php.net)

Conecta tu tienda PrestaShop con **Carrefour Hub España** (Mirakl) sin pagar fees mensuales de un SaaS. Sube tu catálogo, sincroniza stock en tiempo real, recibe los pedidos Carrefour como pedidos nativos de PrestaShop. Gratis, open-source y mantenido.

> **Estado**: trabajo en curso. Primera release estable (v1.0.0) objetivo **junio 2026**.

👉 [Read this in English](README.md)

## Por qué

- Shoppingfeed, Lengow e Iziflux cobran **€100-600/mes** solo por operar.
- El antiguo módulo comunitario (Activesoft 2018) está abandonado y no soporta versiones modernas de PrestaShop.
- Los sellers con PS que querían vender en Carrefour Hub no tenían alternativa libre y mantenida.

Este módulo cubre ese hueco.

## Qué hace (v1.0.0)

- **Subida de catálogo** a Carrefour Hub vía API Mirakl (ofertas + productos).
- **Sync de stock en tiempo real**: hook en PrestaShop → push a Mirakl con debounce.
- **Importación de pedidos**: pedidos Carrefour aparecen en PrestaShop como pedidos nativos.
- **Multi-tienda real**: cada tienda PS se puede conectar a su propia cuenta Carrefour, credenciales separadas.
- **Dashboard de errores** con botones de reintento: cero fallos silenciosos.
- **Cola de trabajos asíncrona**: operaciones masivas no bloquean tu admin.
- **Logging estructurado** en `var/logs/` y logger de PrestaShop.
- **Toggle sandbox / producción** para test seguro.
- **Compatible con PrestaShop 1.6 → 9.x**.

Más en [ROADMAP.md](ROADMAP.md).

## Inicio rápido

### Para comerciantes

1. Descarga el ZIP desde [Releases](../../releases).
2. En PS admin: **Módulos → Gestor de módulos → Subir un módulo** → selecciona el ZIP.
3. Instala y abre **Vender en Carrefour → Configuración**.
4. Pega tu API key de Mirakl y el endpoint.
5. Prueba la conexión y empieza a subir ofertas.

Guías detalladas en [`docs/`](docs/).

### Para developers

```bash
git clone https://github.com/rafapastor/prestashop-carrefour.git
cd prestashop-carrefour
make dev          # levanta PrestaShop + MySQL vía Docker
# http://localhost:8081/admindev
# Admin:   admin@prestashop.com / prestashop_demo
make test         # PHPUnit
make lint         # estándares de código
make format       # aplica estándares de PS
```

Ver [CONTRIBUTING.md](CONTRIBUTING.md) para la guía completa del contributor.

## Documentación

- [Instalación](docs/installation.md)
- [Configuración](docs/configuration.md)
- [Multi-tienda](docs/multishop.md)
- [Solución de problemas](docs/troubleshooting.md)
- [FAQ](docs/faq.md)

## Comunidad y soporte

- 🐛 **Bugs**: [abre un issue](../../issues/new?template=bug_report.yml)
- 💡 **Propuestas de mejora**: [abre un issue](../../issues/new?template=feature_request.yml)
- 💬 **Preguntas**: [GitHub Discussions](../../discussions)
- 📬 **Soporte profesional, setup y desarrollos a medida**: [carrefour@smart-shop-ai.com](mailto:carrefour@smart-shop-ai.com) o [agenda una llamada de 30 min](https://calendly.com/rafapas22/30min)

El soporte de la comunidad es best-effort. Si necesitas tiempos de respuesta garantizados, integración llave en mano u onboarding completo, hay servicios de pago disponibles.

## Licencia

Este proyecto está licenciado bajo la **GNU AGPL v3.0** — ver [LICENSE](LICENSE).

AGPL protege a la comunidad: si modificas el módulo y lo usas como servicio de red, tienes que compartir tus cambios. Para casos de uso comercial donde AGPL no encaja, contacta al maintainer para licencia comercial.

## Agradecimientos

Basado en el módulo abandonado Carrefour de Activesoft (2018), reescrito por completo para versiones modernas de PrestaShop.
