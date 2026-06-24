-- Balance the 200 students in To Assign between AIS and Pure Accounting.
-- DANIAL RAHMAN remains excluded from To Assign.

START TRANSACTION;

UPDATE student
SET student_course = CASE
    WHEN CAST(student_matric_no AS UNSIGNED) BETWEEN 300001 AND 300100
        THEN 'Bachelor of Accounting (Information Systems) (Hons)'
    WHEN CAST(student_matric_no AS UNSIGNED) BETWEEN 300101 AND 300200
        THEN 'Bachelor of Accounting (Hons)'
    ELSE student_course
END
WHERE CAST(student_matric_no AS UNSIGNED) BETWEEN 300001 AND 300200;

COMMIT;
