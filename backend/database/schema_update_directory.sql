-- =============================================================
-- VidaLivre — Atualização do schema: directory_entries
-- Arquivo: schema_update_directory.sql
--
-- Execute este arquivo caso o schema base ainda não contenha
-- as colunas/índices abaixo. É seguro rodar mais de uma vez
-- (instruções usam IF NOT EXISTS / IF EXISTS quando possível).
-- =============================================================

-- 1. Garante que is_active tenha DEFAULT 0 para novos cadastros
--    aguardando revisão (o valor padrão original era 1).
--    Ajuste conforme a política do projeto.
ALTER TABLE directory_entries
    MODIFY COLUMN is_active TINYINT(1) NOT NULL DEFAULT 0;

-- 2. Índice composto para buscas por tipo + ativo (query mais comum)
ALTER TABLE directory_entries
    ADD INDEX IF NOT EXISTS idx_directory_entries_type_active (entry_type, is_active);

-- 3. Índice composto para buscas por estado + ativo
ALTER TABLE directory_entries
    ADD INDEX IF NOT EXISTS idx_directory_entries_state_active (state, is_active);

-- 4. Índice para buscas de texto em specialty (útil para LIKE '%x%' via fulltext)
--    Remova se já existir ou se não usar MySQL >= 5.6 com InnoDB fulltext.
ALTER TABLE directory_entries
    ADD FULLTEXT INDEX IF NOT EXISTS ft_directory_entries_specialty_name (specialty, name);

-- =============================================================
-- Verificação (opcional): lista entradas pendentes de revisão
-- =============================================================
-- SELECT id, entry_type, name, slug, created_at
-- FROM directory_entries
-- WHERE is_active = 0
-- ORDER BY created_at DESC;
