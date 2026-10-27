-- Laravel's `artisan test --parallel` gives each worker its own database
-- (construye_test_1, construye_test_2, …). The container's MYSQL_USER grant only covers
-- construye_test, so without this the workers cannot create or use their own and
-- every parallel run fails on a missing table.
--
-- It also makes the suite safe to run twice at once: two concurrent serial
-- runs share construye_test and wipe each other's schema mid-run.
GRANT ALL PRIVILEGES ON `construye_test%`.* TO 'construye'@'%';
GRANT CREATE, DROP ON *.* TO 'construye'@'%';
FLUSH PRIVILEGES;
