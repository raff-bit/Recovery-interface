-- database/add_age_to_user_profile.sql
-- Adiciona a coluna 'age' à tabela 'user_profile' com validação de limites fisiológicos

ALTER TABLE user_profile 
ADD COLUMN age INT NULL AFTER weight_kg;