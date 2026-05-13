<?php
/**
 * Notification Types & Configuration
 * Enterprise-grade enumeration for all system notification types
 * COA-Compliant: Audit-ready, immutable, traceable
 *
 * CHANGELOG:
 *   v1.1 — Added USER_DATA_UPDATED type for profile / CAO employee record changes.
 *           Registered in getDefaultPriority(), getDefaultRecipients(), getTitle(),
 *           isValid(), and getAllTypes().
 */

class NotificationTypes {
    // ===== NOTIFICATION TYPE CONSTANTS =====
    // Staff → Admin Notifications
    const BORROW_REQUEST_SUBMITTED = 'BORROW_REQUEST_SUBMITTED';
    const RETURN_REQUEST_SUBMITTED = 'RETURN_REQUEST_SUBMITTED';
    const DAMAGE_REPORTED          = 'DAMAGE_REPORTED';

    // Admin → Staff Notifications
    const BORROW_REQUEST_APPROVED  = 'BORROW_REQUEST_APPROVED';
    const BORROW_REQUEST_DENIED    = 'BORROW_REQUEST_DENIED';
    const ITEM_RELEASED            = 'ITEM_RELEASED';
    const RETURN_REQUEST_APPROVED  = 'RETURN_REQUEST_APPROVED';
    const RETURN_REQUEST_REJECTED  = 'RETURN_REQUEST_REJECTED';

    // System → Staff & Admin Notifications (Automated)
    const DUE_DATE_REMINDER        = 'DUE_DATE_REMINDER';
    const OVERDUE_ALERT            = 'OVERDUE_ALERT';
    const ACCOUNTABILITY_REQUIRED  = 'ACCOUNTABILITY_REQUIRED';
    const SYSTEM_ESCALATION        = 'SYSTEM_ESCALATION';

    // Cron-generated or incident notifications
    const OVERDUE_WARNING           = 'OVERDUE_WARNING';
    const OVERDUE_CRITICAL          = 'OVERDUE_CRITICAL';
    const ADMIN_OVERDUE_ESCALATION  = 'ADMIN_OVERDUE_ESCALATION';
    const INCIDENT_CREATED          = 'INCIDENT_CREATED';
    const INCIDENT_AUTO_CREATED     = 'INCIDENT_AUTO_CREATED';
    const INCIDENT_RESOLVED         = 'INCIDENT_RESOLVED';

    /**
     * -----------------------------------------------------------------------
     * USER / EMPLOYEE DATA CHANGE NOTIFICATION
     * -----------------------------------------------------------------------
     * Fired whenever a row that belongs to (or identifies) a specific system
     * user is mutated in either the `user` table or the `cao_employee` table.
     *
     * Watched fields:
     *   user         → first_name, last_name
     *   cao_employee → end_user_id_number, FIRSTNAME, MIDDLENAME, LASTNAME
     *
     * The notification is delivered to:
     *   • The affected user  (PRIORITY_NORMAL → in-app + email)
     *   • All ADMIN accounts (same payload, so they see who was changed)
     *
     * Handled by: user_data_change_helper.php → processUserDataChange()
     */
    const USER_DATA_UPDATED = 'USER_DATA_UPDATED';

    // ===== PRIORITY MATRIX =====
    const PRIORITY_LOW      = 'low';       // In-app only
    const PRIORITY_NORMAL   = 'normal';    // In-app + Email
    const PRIORITY_HIGH     = 'high';      // In-app + Email + SMS
    const PRIORITY_CRITICAL = 'critical';  // All channels + escalation

    // ===== DELIVERY CHANNELS =====
    const CHANNEL_IN_APP = 'in_app';
    const CHANNEL_EMAIL  = 'email';
    const CHANNEL_SMS    = 'sms';
    const CHANNEL_PUSH   = 'push';

    // ===== SENDER ROLES =====
    const ROLE_STAFF  = 'STAFF';
    const ROLE_ADMIN  = 'ADMIN';
    const ROLE_SYSTEM = 'SYSTEM';

    // ===== RECIPIENT ROLES =====
    const RECIPIENT_STAFF      = 'STAFF';
    const RECIPIENT_ADMIN      = 'ADMIN';
    const RECIPIENT_MANAGEMENT = 'MANAGEMENT';

    // ===== ENTITY TYPES =====
    const ENTITY_BORROW_REQUEST = 'borrow_request';
    const ENTITY_RETURN_REQUEST = 'return_request';
    const ENTITY_INCIDENT_REPORT = 'incident_report';
    const ENTITY_ITEM = 'item';

    /**
     * Get default priority for a notification type.
     */
    public static function getDefaultPriority($type) {
        $priorities = [
            self::BORROW_REQUEST_SUBMITTED  => self::PRIORITY_NORMAL,
            self::RETURN_REQUEST_SUBMITTED  => self::PRIORITY_NORMAL,
            self::DAMAGE_REPORTED           => self::PRIORITY_HIGH,
            self::BORROW_REQUEST_APPROVED   => self::PRIORITY_NORMAL,
            self::BORROW_REQUEST_DENIED     => self::PRIORITY_NORMAL,
            self::ITEM_RELEASED             => self::PRIORITY_NORMAL,
            self::RETURN_REQUEST_APPROVED   => self::PRIORITY_NORMAL,
            self::RETURN_REQUEST_REJECTED   => self::PRIORITY_HIGH,
            self::DUE_DATE_REMINDER         => self::PRIORITY_NORMAL,
            self::OVERDUE_ALERT             => self::PRIORITY_CRITICAL,
            self::ACCOUNTABILITY_REQUIRED   => self::PRIORITY_CRITICAL,
            self::SYSTEM_ESCALATION         => self::PRIORITY_HIGH,
            self::OVERDUE_WARNING           => self::PRIORITY_NORMAL,
            self::OVERDUE_CRITICAL          => self::PRIORITY_HIGH,
            self::ADMIN_OVERDUE_ESCALATION  => self::PRIORITY_CRITICAL,
            self::INCIDENT_CREATED          => self::PRIORITY_HIGH,
            self::INCIDENT_AUTO_CREATED     => self::PRIORITY_CRITICAL,
            self::INCIDENT_RESOLVED         => self::PRIORITY_NORMAL,
            // -- NEW --
            // NORMAL → delivers in-app + email; non-blocking for the actor.
            self::USER_DATA_UPDATED         => self::PRIORITY_NORMAL,
        ];

        return $priorities[$type] ?? self::PRIORITY_NORMAL;
    }

    /**
     * Get default recipient role(s) for a notification type.
     */
    public static function getDefaultRecipients($type) {
        $recipients = [
            self::BORROW_REQUEST_SUBMITTED  => [self::ROLE_ADMIN],
            self::RETURN_REQUEST_SUBMITTED  => [self::ROLE_ADMIN],
            self::DAMAGE_REPORTED           => [self::ROLE_ADMIN],
            self::BORROW_REQUEST_APPROVED   => [self::ROLE_STAFF],
            self::BORROW_REQUEST_DENIED     => [self::ROLE_STAFF],
            self::ITEM_RELEASED             => [self::ROLE_STAFF],
            self::RETURN_REQUEST_APPROVED   => [self::ROLE_STAFF],
            self::RETURN_REQUEST_REJECTED   => [self::ROLE_STAFF],
            self::DUE_DATE_REMINDER         => [self::ROLE_STAFF],
            self::OVERDUE_ALERT             => [self::ROLE_STAFF, self::ROLE_ADMIN],
            self::ACCOUNTABILITY_REQUIRED   => [self::ROLE_STAFF, self::ROLE_ADMIN],
            self::SYSTEM_ESCALATION         => [self::ROLE_ADMIN],
            self::OVERDUE_WARNING           => [self::ROLE_STAFF],
            self::OVERDUE_CRITICAL          => [self::ROLE_STAFF],
            self::ADMIN_OVERDUE_ESCALATION  => [self::ROLE_ADMIN],
            self::INCIDENT_CREATED          => [self::ROLE_STAFF],
            self::INCIDENT_AUTO_CREATED     => [self::ROLE_ADMIN],
            self::INCIDENT_RESOLVED         => [self::ROLE_STAFF, self::ROLE_ADMIN],
            // -- NEW --
            // The affected user receives it personally; admins also see it for oversight.
            self::USER_DATA_UPDATED         => [self::ROLE_STAFF, self::ROLE_ADMIN],
        ];

        return $recipients[$type] ?? [self::ROLE_ADMIN];
    }

    /**
     * Get delivery channels based on priority.
     */
    public static function getChannelsForPriority($priority) {
        $channelMap = [
            self::PRIORITY_LOW      => [self::CHANNEL_IN_APP],
            self::PRIORITY_NORMAL   => [self::CHANNEL_IN_APP, self::CHANNEL_EMAIL],
            self::PRIORITY_HIGH     => [self::CHANNEL_IN_APP, self::CHANNEL_EMAIL, self::CHANNEL_SMS],
            self::PRIORITY_CRITICAL => [self::CHANNEL_IN_APP, self::CHANNEL_EMAIL, self::CHANNEL_SMS, self::CHANNEL_PUSH],
        ];

        return $channelMap[$priority] ?? [self::CHANNEL_IN_APP];
    }

    /**
     * Get human-readable title for notification type.
     */
    public static function getTitle($type) {
        $titles = [
            self::BORROW_REQUEST_SUBMITTED  => 'New Borrow Request Pending',
            self::BORROW_REQUEST_APPROVED   => 'Borrow Request Approved',
            self::BORROW_REQUEST_DENIED     => 'Borrow Request Denied',
            self::ITEM_RELEASED             => 'Item Released for Borrowing',
            self::RETURN_REQUEST_SUBMITTED  => 'Return Request Awaiting Inspection',
            self::RETURN_REQUEST_APPROVED   => 'Return Approved',
            self::RETURN_REQUEST_REJECTED   => 'Return Rejected - Action Required',
            self::DAMAGE_REPORTED           => 'Damage Report Submitted',
            self::DUE_DATE_REMINDER         => 'Upcoming Due Date',
            self::OVERDUE_ALERT             => '🚨 ITEM OVERDUE - Immediate Action Required',
            self::ACCOUNTABILITY_REQUIRED   => '⚠️ Accountability Issue - Response Required',
            self::SYSTEM_ESCALATION         => 'SLA Escalation - Admin Action Needed',
            self::OVERDUE_WARNING           => 'Upcoming Due Date - Please Return Item',
            self::OVERDUE_CRITICAL          => '⚠️ Overdue Item - Administrative Action Required',
            self::ADMIN_OVERDUE_ESCALATION  => '📢 Overdue Escalation - Admin Attention',
            self::INCIDENT_CREATED          => 'Incident Report Created',
            self::INCIDENT_AUTO_CREATED     => 'Automated Incident Created',
            self::INCIDENT_RESOLVED         => 'Incident Resolved',
            // -- NEW --
            self::USER_DATA_UPDATED         => '📋 Your Profile / Account Data Was Updated',
        ];

        return $titles[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Validate notification type.
     */
    public static function isValid($type) {
        return in_array($type, self::getAllTypes(), true);
    }

    /**
     * Get all notification types.
     */
    public static function getAllTypes() {
        return [
            self::BORROW_REQUEST_SUBMITTED,
            self::BORROW_REQUEST_APPROVED,
            self::BORROW_REQUEST_DENIED,
            self::ITEM_RELEASED,
            self::RETURN_REQUEST_SUBMITTED,
            self::RETURN_REQUEST_APPROVED,
            self::RETURN_REQUEST_REJECTED,
            self::DAMAGE_REPORTED,
            self::DUE_DATE_REMINDER,
            self::OVERDUE_ALERT,
            self::ACCOUNTABILITY_REQUIRED,
            self::SYSTEM_ESCALATION,
            self::OVERDUE_WARNING,
            self::OVERDUE_CRITICAL,
            self::ADMIN_OVERDUE_ESCALATION,
            self::INCIDENT_CREATED,
            self::INCIDENT_AUTO_CREATED,
            self::INCIDENT_RESOLVED,
            // -- NEW --
            self::USER_DATA_UPDATED,
        ];
    }
}