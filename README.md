Magento shell scripts collections
=================================

cleanup.php - Remove orphan catalog flat tables / catalogsearch fulltext records.
delete.php  - Delete website/group/store.
backup.sh   - Backup magento DB (can ignore some tables).


Clean-up
========

Usage:
    php -f cleanup.php [--info] [--cleanup]
        --info | --dry-run    - List not (more) used catalog_(category|store)_flat tables,
        --cleanup             - Cleanup tables:
                                    drop not (more) used catalog_(category|store)_flat tables,
                                    delete orphan records from catalogsearch_(fulltext|results)
Delete website/store/group
==========================
Usage:
    php -f delete.php --list --(website|group|store) <id> --backupdb --cleanup

        --list                  - Show all websites/stores
        --(website|group|store) - Delete store OR website OR website group with <id> ID (exclusive OR)
        --backupdb [--force]    - Backup database before delete store,website or group
                                  !!! Very slow operation, use backup.sh/mysqldump instead of Mage::Backup.


