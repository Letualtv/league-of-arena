-- Limpia partidas y campeones detectados de la API de la temporada anterior.
-- Mantiene los campeones marcados manualmente (marcado_manual = 1).
-- Tras ejecutar este script, los usuarios deben pulsar "Actualizar perfil"
-- para que el sync re-importe sus partidas de la temporada actual (queue 1750).

-- 1. Vaciar el historial de partidas
TRUNCATE TABLE partidas_arena;

-- 2. Borrar SOLO los campeones detectados por la API (mantiene los marcados a mano)
DELETE FROM campeones_ganados WHERE marcado_manual = 0;

-- 3. Resetear logros desbloqueados que se basen en partidas/victorias
--    (se recalculan automáticamente al siguiente sync)
-- DELETE FROM logros_desbloqueados;  -- descomenta si quieres reset total
