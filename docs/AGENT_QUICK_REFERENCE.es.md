# ⚡ Guía de Referencia Rápida para Desarrolladores e IAs

Esta "Hoja de Trucos" está diseñada para un onboarding rápido y un desarrollo de alta velocidad.

## 🚀 Comandos Core

| Comando | Propósito | ¿Cuándo usarlo? |
|---------|-----------|-----------------|
| `bash bin/make-crud.sh <Res> <Dom> '<fields>'` | **Scaffold Module (recomendado)** | Al empezar un nuevo recurso CRUD — shell-safe, funciona en no-TTY. |
| `php spark make:crud {Nombre}` | **Scaffold Module (interactivo)** | Cuando quieras ser consultado por cada campo. |
| `php spark module:check {Nombre} --domain {Dom}` | **Validar wiring** | Justo después del scaffold. |
| `php spark migrate` | **Aplicar cambios DB** | Después del scaffold, revisa la migración y aplícala. |
| `pkill -f 'spark serve'; php spark serve &` | **Reiniciar servidor** | Obligatorio tras el scaffold — los archivos de ruta nuevos no se cargan en caliente. |
| `php spark swagger:generate` | **Actualizar OpenAPI** | Al añadir endpoints o DTOs. |
| `composer quality` | **Check de Salud Total**| Antes de hacer push de cualquier código. |
| `composer cs-fix` | **Corregir Estilo** | Para auto-formatear tu código. |

## 🏗️ Sintaxis de Scaffolding

Firma: `bash bin/make-crud.sh <Resource> <Domain> '<Fields>' [SoftDelete=yes] [Route]`

**Tipos Disponibles:** `string`, `text`, `int`, `bool`, `decimal`, `email`, `date`, `datetime`, `fk`, `json`.
**Opciones Comunes:** `required`, `nullable`, `searchable`, `filterable`, `fk:tableName`.

*Ejemplo:*
`bash bin/make-crud.sh Producto Catalogo 'nombre:string:required|searchable,categoria_id:fk:categorias:required' yes`

## ✅ Lista de Estándares de Calidad

1.  **Inmutabilidad:** Usa siempre `readonly class` para los DTOs.
2.  **DTO-First:** Sin mapeo directo en Controladores; usa `RequestDataCollector`.
3.  **Auditoría:** Usa el trait `Auditable` para cualquier modelo con datos sensibles.
4.  **Tests:** Los nuevos servicios deben incluir tests unitarios; los controladores, tests de feature.
5.  **Docs:** Asegúrate de que los tags y resúmenes de OpenAPI sean claros y agrupados por Dominio.

## 📁 Mapa de Estructura de Archivos (API por Capas)

- `app/Controllers/Api/V1/{Dominio}/` -> Punto de entrada.
- `app/DTO/Request/{Dominio}/` -> Validación de entrada.
- `app/DTO/Response/{Dominio}/` -> Transformación de salida.
- `app/Interfaces/{Dominio}/` -> Contratos de servicio.
- `app/Services/{Dominio}/` -> Lógica de negocio.
- `app/Models/` -> Orquestación de base de datos.
- `app/Documentation/{Dominio}/` -> Definiciones de OpenAPI.
