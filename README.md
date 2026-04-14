# AgentKit

Plugin de WordPress para añadir un asistente con IA al sitio usando RAG sobre contenido propio, streaming SSE y soporte multi-provider.

Estado actual: base funcional del MVP en desarrollo activo.

## Características actuales

- Chat widget público
- Shortcode `[agentkit]`
- Endpoint REST de chat JSON y streaming SSE
- RAG base sobre contenido público de WordPress
- Indexación de archivos
  - `txt`
  - `md`
  - `csv`
  - `pdf`
  - `docx`
  - `pptx`
- Providers disponibles
  - OpenAI
  - Anthropic
  - Gemini
  - OpenRouter
- Provider fallback
- Panel admin funcional
  - Dashboard con métricas y gráficas
  - General
  - Models
  - Security
  - Knowledge Base
  - Conversations
- API keys cifradas
- Rate limiting por IP y sesión
- Helpers locales para `php` CLI y `composer` usando Docker

## Requisitos

- WordPress 6.0+
- PHP 8.1+
- MySQL/MariaDB
- Docker disponible localmente para usar `./tools/php` y `./tools/composer`

## Instalación

Clona o copia el plugin en:

```bash
public_html/wp-content/plugins/wp-agent-kit
```

Instala dependencias PHP:

```bash
cd public_html/wp-content/plugins/wp-agent-kit
./tools/composer install
```

Activa el plugin desde WordPress:

```text
/wp-admin/plugins.php
```

## Desarrollo local

Este proyecto incluye wrappers para no depender de `php` CLI instalado en el sistema.

### PHP CLI

```bash
./tools/php -v
```

### Lint PHP

```bash
./tools/php-lint
```

### Composer

```bash
./tools/composer install
./tools/composer show
```

### Frontend

Dependencias JS:

```bash
npm install
```

Scripts:

```bash
npm run dev
npm run build
```

Nota: el repo ya contiene `dist/` funcional para que el plugin cargue assets incluso sin correr build.

## Uso básico

### 1. Configura el provider

En el panel `AgentKit` dentro de `wp-admin` configura:

- provider principal
- API key
- modelo de chat
- modelo de embeddings
- provider fallback opcional

### 2. Indexa contenido

Opciones actuales:

- reindexar contenido público del sitio
- registrar archivos por `attachment_id`

### 3. Inserta el chat

Usa el shortcode:

```text
[agentkit]
```

## Arquitectura resumida

### Backend PHP

- `includes/Core/` bootstrap del plugin
- `includes/API/` endpoints REST
- `includes/RAG/` indexación, búsqueda y contexto
- `includes/AI/` providers, prompts y streaming
- `includes/Security/` nonce, rate limit, sanitización y cifrado
- `includes/Stats/` logging y agregación diaria
- `includes/Parsers/` extracción de texto por tipo de archivo

### Frontend

- `dist/widget.js` widget público actual
- `dist/admin.js` panel admin actual
- `src/` contiene la base del frontend fuente para evolución posterior

## Endpoints principales

- `POST /wp-json/agentkit/v1/chat`
- `POST /wp-json/agentkit/v1/chat-stream`
- `GET|POST /wp-json/agentkit/v1/settings`
- `POST /wp-json/agentkit/v1/index`
- `GET /wp-json/agentkit/v1/stats`
- `GET|DELETE /wp-json/agentkit/v1/conversations`
- `GET /wp-json/agentkit/v1/conversations/messages?id={id}`
- `GET|POST|DELETE /wp-json/agentkit/v1/files`
- `POST /wp-json/agentkit/v1/files/reindex`
- `POST /wp-json/agentkit/v1/providers/test`

## Estado del proyecto

Implementado:

- bootstrap WordPress
- schema de base de datos
- settings seguras
- widget funcional
- streaming SSE
- admin funcional
- gráficas básicas en dashboard
- localización base por idioma configurado

Pendiente o en evolución:

- admin SPA completa en `src/admin`
- bloque Gutenberg
- widget/sidebar nativo de WordPress
- exportación CSV completa
- analytics más avanzadas
- test suite automatizada

## Git y repositorio

Repositorio objetivo:

```text
https://github.com/JoseCortezz25/wp-agentkit.git
```

## Licencia

Dual:

- MIT
- GPL-2.0-or-later
