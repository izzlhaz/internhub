-- Activate internship records for every student except DANIAL RAHMAN.
-- Existing completed placements are reopened as Active for this demo dataset.

START TRANSACTION;

UPDATE internship i
JOIN student s ON s.student_id = i.student_id
SET i.internship_status = 'Active'
WHERE UPPER(TRIM(s.student_name)) <> 'DANIAL RAHMAN'
  AND i.internship_status IN ('Accepted', 'Completed');

UPDATE internship i
JOIN student s ON s.student_id = i.student_id
SET i.internship_status = 'Pending'
WHERE UPPER(TRIM(s.student_name)) = 'DANIAL RAHMAN'
  AND i.internship_status IN ('Accepted', 'Active', 'Completed');

CREATE TEMPORARY TABLE active_student_candidates AS
SELECT
    s.student_id,
    s.student_course,
    ROW_NUMBER() OVER (
        PARTITION BY CASE WHEN s.student_course LIKE '%Information Systems%' THEN 'AIS' ELSE 'PURE' END
        ORDER BY s.student_id
    ) AS programme_row,
    ROW_NUMBER() OVER (ORDER BY s.student_id) AS overall_row
FROM student s
WHERE UPPER(TRIM(s.student_name)) <> 'DANIAL RAHMAN'
  AND NOT EXISTS (
      SELECT 1
      FROM internship i
      WHERE i.student_id = s.student_id
        AND i.internship_status IN ('Accepted', 'Active', 'Completed')
  );

CREATE TEMPORARY TABLE ais_lecturer_pool AS
SELECT
    l.lecturer_id,
    ROW_NUMBER() OVER (ORDER BY l.lecturer_id) AS pool_row
FROM lecturer l
JOIN user u ON u.user_id = l.user_id
WHERE u.user_status = 'Active'
  AND l.lecturer_programme LIKE '%Information Systems%';

CREATE TEMPORARY TABLE pure_lecturer_pool AS
SELECT
    l.lecturer_id,
    ROW_NUMBER() OVER (ORDER BY l.lecturer_id) AS pool_row
FROM lecturer l
JOIN user u ON u.user_id = l.user_id
WHERE u.user_status = 'Active'
  AND l.lecturer_programme NOT LIKE '%Information Systems%';

CREATE TEMPORARY TABLE job_pool AS
SELECT
    j.job_id,
    j.company_id,
    j.job_title,
    ROW_NUMBER() OVER (ORDER BY j.job_id) AS pool_row
FROM jobposting j
JOIN company c ON c.company_id = j.company_id
WHERE j.job_status = 'Active'
  AND c.company_approval_status = 'Approved';

SET @ais_lecturer_count = (SELECT COUNT(*) FROM ais_lecturer_pool);
SET @pure_lecturer_count = (SELECT COUNT(*) FROM pure_lecturer_pool);
SET @job_count = (SELECT COUNT(*) FROM job_pool);

INSERT INTO internship (
    student_id,
    company_id,
    lecturer_id,
    job_id,
    internship_field,
    internship_start_date,
    internship_end_date,
    internship_status
)
SELECT
    candidates.student_id,
    jobs.company_id,
    CASE
        WHEN candidates.student_course LIKE '%Information Systems%' THEN ais.lecturer_id
        ELSE pure.lecturer_id
    END,
    jobs.job_id,
    jobs.job_title,
    COALESCE(r.resume_internship_start_date, '2026-07-01'),
    COALESCE(r.resume_internship_end_date, '2026-12-31'),
    'Active'
FROM active_student_candidates candidates
JOIN job_pool jobs
  ON jobs.pool_row = MOD(candidates.overall_row - 1, @job_count) + 1
LEFT JOIN ais_lecturer_pool ais
  ON candidates.student_course LIKE '%Information Systems%'
 AND ais.pool_row = MOD(candidates.programme_row - 1, @ais_lecturer_count) + 1
LEFT JOIN pure_lecturer_pool pure
  ON candidates.student_course NOT LIKE '%Information Systems%'
 AND pure.pool_row = MOD(candidates.programme_row - 1, @pure_lecturer_count) + 1
LEFT JOIN resume r ON r.student_id = candidates.student_id;

DROP TEMPORARY TABLE active_student_candidates;
DROP TEMPORARY TABLE ais_lecturer_pool;
DROP TEMPORARY TABLE pure_lecturer_pool;
DROP TEMPORARY TABLE job_pool;

COMMIT;
