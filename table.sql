-- Task Manager schema extensions
-- review_status was part of the Ace702 provider_dashboard customization (not in core OpenEMR).

#IfMissingColumn forms review_status
ALTER TABLE `forms` ADD `review_status` TINYINT(1) NULL DEFAULT NULL COMMENT 'Task Manager review: null/0=Not Ready, 1=Ready/InProgress';
#EndIf

-- Permanent top tab for all staff (option_id pdb kept for upgrade compatibility).
#IfNotRow2D list_options list_id default_open_tabs option_id pdb
INSERT INTO `list_options` (`list_id`, `notes`, `title`, `seq`, `option_id`, `activity`) VALUES ('default_open_tabs', 'interface/modules/custom_modules/oe-provider-dashboard/public/index.php', 'Task Manager', 5, 'pdb', '1');
#EndIf

-- Rename permanent tab if installed under old "Provider Dashboard" branding (does not force activity).
#IfRow2D list_options list_id default_open_tabs option_id pdb
UPDATE `list_options` SET `title` = 'Task Manager' WHERE `list_id` = 'default_open_tabs' AND `option_id` = 'pdb' AND `title` = 'Provider Dashboard';
#EndIf

#IfNotTable module_task_manager_tasks
CREATE TABLE `module_task_manager_tasks` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `pid` BIGINT(20) NOT NULL,
  `rule_code` VARCHAR(64) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'open',
  `assignee_user_id` INT(11) NULL,
  `created_by_user_id` INT(11) NULL,
  `due_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `completion_source` VARCHAR(32) NULL,
  `fix_path` VARCHAR(512) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tm_tasks_assignee_status` (`assignee_user_id`, `status`),
  KEY `idx_tm_tasks_pid_status` (`pid`, `status`),
  KEY `idx_tm_tasks_rule_status` (`rule_code`, `status`),
  KEY `idx_tm_tasks_due` (`due_at`)
) ENGINE=InnoDB COMMENT='Task Manager chart completeness work queue';
#EndIf

-- Nightly completeness rescan (idempotent)
#IfNotRow background_services name TaskManagerCompleteness
INSERT INTO `background_services`
(`name`, `title`, `active`, `running`, `next_run`, `execute_interval`, `function`, `require_once`, `sort_order`)
VALUES
('TaskManagerCompleteness', 'Task Manager Completeness Scan', 1, 0, NOW(), 1440, 'task_manager_completeness_scan', '/interface/modules/custom_modules/oe-provider-dashboard/scripts/completeness_background.php', 100);
#EndIf
