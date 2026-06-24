USE `internhub`;

ALTER TABLE `internship`
  ADD COLUMN IF NOT EXISTS `job_id` int(11) DEFAULT NULL AFTER `lecturer_id`,
  ADD KEY IF NOT EXISTS `idx_internship_job` (`job_id`);

ALTER TABLE `companyevaluation`
  ADD COLUMN IF NOT EXISTS `internship_id` int(11) DEFAULT NULL AFTER `ce_id`,
  ADD UNIQUE KEY IF NOT EXISTS `internship_id` (`internship_id`);

ALTER TABLE `lecturerevaluation`
  ADD COLUMN IF NOT EXISTS `internship_id` int(11) DEFAULT NULL AFTER `le_id`,
  MODIFY `le_total_score` decimal(5,2) DEFAULT NULL,
  ADD UNIQUE KEY IF NOT EXISTS `internship_id` (`internship_id`);

ALTER TABLE `report`
  ADD COLUMN IF NOT EXISTS `internship_id` int(11) DEFAULT NULL AFTER `report_id`,
  MODIFY `ce_id` int(11) DEFAULT NULL,
  MODIFY `le_id` int(11) DEFAULT NULL,
  MODIFY `report_total_score` decimal(5,2) DEFAULT NULL,
  MODIFY `report_grade` enum('A+','A','A-','B+','B','B-','C+','C','C-','D+','D','F') DEFAULT NULL,
  ADD UNIQUE KEY IF NOT EXISTS `internship_id` (`internship_id`);

ALTER TABLE `internship`
  ADD CONSTRAINT `fk_internship_job` FOREIGN KEY (`job_id`) REFERENCES `jobposting` (`job_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `companyevaluation`
  ADD CONSTRAINT `fk_ce_internship` FOREIGN KEY (`internship_id`) REFERENCES `internship` (`internship_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `lecturerevaluation`
  ADD CONSTRAINT `fk_le_internship` FOREIGN KEY (`internship_id`) REFERENCES `internship` (`internship_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `report`
  ADD CONSTRAINT `fk_report_internship` FOREIGN KEY (`internship_id`) REFERENCES `internship` (`internship_id`) ON DELETE CASCADE ON UPDATE CASCADE;
