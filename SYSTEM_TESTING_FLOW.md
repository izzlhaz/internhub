ALTER TABLE jobposting
    ADD COLUMN IF NOT EXISTS job_poster_name varchar(255) DEFAULT NULL AFTER job_requirement,
    ADD COLUMN IF NOT EXISTS job_poster_type varchar(50) DEFAULT NULL AFTER job_poster_name,
    ADD COLUMN IF NOT EXISTS job_poster_data longblob DEFAULT NULL AFTER job_poster_type,
    ADD COLUMN IF NOT EXISTS job_poster_uploaded_at datetime DEFAULT NULL AFTER job_poster_data;
