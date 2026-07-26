# Plan de Implementación: Arreglo de Contaminación de Base de Datos del API

> **Para trabajadores agenciales:** HABILIDAD SUB-REQUERIDA: Usar superpowers:subagent-driven-development (recomendado) o superpowers:executing-plans para implementar este plan tarea por tarea.

**Objetivo:** Alinear el proyecto API con el contrato de base de datos `ci4_website_builder`, eliminar valores predeterminados obsoletos de `ci4_api`, y reconstruir la base de datos en vivo para que las tablas `cms_` ya no aparezcan en el esquema del API.

**Arquitectura:** El API ya es propietario del esquema hub/RBAC; el problema es la desviación de configuración entre `.env`, valores predeterminados de Docker y configuración PHP. Haremos que el nombre de la base de datos sea consistente en todos los archivos de tiempo de ejecución, luego restableceremos y recrearemos el esquema MySQL en vivo usando las migraciones y sembradores del API, y finalmente verificaremos que el esquema resultante contenga solo tablas del API.

**Stack de Tecnología:** CodeIgniter 4, Bash, Docker Compose, MySQL, PHPUnit

---

*Traducción pendiente — Ver archivo EN para detalles completos.*
