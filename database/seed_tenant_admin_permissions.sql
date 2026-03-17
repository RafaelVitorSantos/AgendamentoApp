-- Atribui todas as permissões ao role tenant_admin (administrador da empresa).
-- Execute este script se o banco foi criado antes de existir o INSERT no schema.sql
-- ou se administradores não têm acesso aos módulos.

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = 'tenant_admin';
