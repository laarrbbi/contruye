-- Laravel's `artisan test --parallel` gives each worker its own database
-- (ebni_test_1, ebni_test_2, …). The container's MYSQL_USER grant only covers
-- ebni_test, so without this the workers cannot create or use their own and
-- every parallel run fails on a missing table.
--
-- It also makes the suite safe to run twice at once: two concurrent serial
-- runs share ebni_test and wipe each other's schema mid-run.
GRANT ALL PRIVILEGES ON `ebni_test%`.* TO 'ebni'@'%';
GRANT CREATE, DROP ON *.* TO 'ebni'@'%';
FLUSH PRIVILEGES;
