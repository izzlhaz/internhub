-- Additional active coordinator account for InternHub.

INSERT INTO user (
    user_name,
    user_email,
    user_password,
    user_status,
    user_created_by,
    user_role,
    user_must_change_password,
    user_ic_password_initialized
) VALUES (
    'InternHub Coordinator',
    'coordinator@internhub.local',
    '$2y$10$81DgCfNt1EXTLGK66gM2oORn5EjcWd3GgE2jXNVzV7qJQvbKkuxZG',
    'Active',
    1,
    'coordinator',
    0,
    0
)
ON DUPLICATE KEY UPDATE
    user_name = VALUES(user_name),
    user_password = VALUES(user_password),
    user_status = 'Active',
    user_role = 'coordinator';
