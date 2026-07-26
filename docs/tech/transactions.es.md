# Transacciones

Las transacciones de base de datos se usan para mantener consistencia en flujos críticos.

Archivos clave:
- `app/Services/Tokens/RefreshTokenService.php`

Notas:
- La rotación de refresh tokens corre dentro de una transacción.
- El refresh token se bloquea con `FOR UPDATE` para evitar uso concurrente.
