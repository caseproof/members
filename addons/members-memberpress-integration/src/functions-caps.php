<?php
/**
 * Capability Functions.
 *
 * @package   MembersIntegrationMemberPress
 * @author    Krista Butler <krista@caseproof.com>
 * @copyright 2021, Caseproof LLC
 * @license   https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 */

namespace Members\Integration\MemberPress;

# Don't execute code if file is accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Returns an array of the MemberPress plugin capabilities.
 *
 * @since  1.0.0
 * @access public
 * @return array
 */
function memberpress_caps() {

	return [
    //General
		'manage_mepr' => [
			'label'       => __( 'Manage MemberPress', 'members' ),
			'description' => __( 'Allows access to manage MemberPress. Roles must have manage_mepr to use any of the other MemberPress capabilties.', 'members' )
		],
    'view_mepr_menu' => [
			'label'       => __( 'View MemberPress Admin Menu', 'members' ),
			'description' => __( 'Allows access to MemberPress Admin Menu items not protected by other capabilties.', 'members' )
		],
    'view_mepr_unauthorized_access' => [
      'label'       => __( 'View MemberPress Unauthorized Access Settings', 'members' ),
      'description' => __( 'Allows access to MemberPress Unauthorized Access settings on posts and pages.', 'members' )
    ],
    'view_mepr_addons' => [
      'label'       => __( 'View MemberPress Addons', 'members' ),
      'description' => __( 'Allows access to view MemberPress Addons.', 'members' )
    ],

    //Settings
    'view_mepr_settings' => [
      'label'       => __( 'View MemberPress Settings', 'members' ),
      'description' => __( 'Allows access to view MemberPress settings.', 'members' )
    ],
    'edit_mepr_settings' => [
      'label'       => __( 'Edit MemberPress Settings', 'members' ),
      'description' => __( 'Allows access to edit MemberPress settings.', 'members' )
    ],

    //Widgets and Blocks
    'use_mepr_widgets' => [
      'label'       => __( 'Use MemberPress Widgets', 'members' ),
      'description' => __( 'Allows access to add and manage MemberPress widgets.', 'members' )
    ],
    'use_mepr_blocks' => [
      'label'       => __( 'Use MemberPress Blocks', 'members' ),
      'description' => __( 'Allows access to use MemberPress Gutenburg Blocks.', 'members' )
    ],

    //Reports
    'view_mepr_reports' => [
      'label'       => __( 'Reports: View MemberPress Reports', 'members' ),
      'description' => __( 'Allows access to MemberPress Reports.', 'members' )
    ],
    'view_mepr_dashboard_report_widget' => [
      'label'       => __( 'Reports: View the MemberPress Reports Dashboard Widget', 'members' ),
      'description' => __( 'Allows access to MemberPress Reports Dashboard widget.', 'members' )
    ],
    'expand_mepr_reports' => [
      'label'       => __( 'Reports: Expand MemberPress Reports', 'members' ),
      'description' => __( 'Allows access to expand MemberPress Reports to see more detailed information. WARNING: Expanded reports may have sensitive information.', 'members' )
    ],
    'export_mepr_reports' => [
      'label'       => __( 'Reports: Export MemberPress Reports', 'members' ),
      'description' => __( 'Allows access to export MemberPress Reports. Roles must have this capability to export any of the reports.', 'members' )
    ],
    'export_mepr_member_reports' => [
      'label'       => __( 'Reports: Export MemberPress Member Reports', 'members' ),
      'description' => __( 'Allows access to export MemberPress Member Reports.', 'members' )
    ],
    'export_mepr_transaction_reports' => [
      'label'       => __( 'Reports: Export MemberPress Transaction Reports', 'members' ),
      'description' => __( 'Allows access to export MemberPress Transaction Reports.', 'members' )
    ],
    'export_mepr_subscription_reports' => [
      'label'       => __( 'Reports: Export MemberPress Subscription Reports', 'members' ),
      'description' => __( 'Allows access to export MemberPress Subscription Reports.', 'members' )
    ],

    //Members
    'view_mepr_members' => [
      'label'       => __( 'Members: View MemberPress Members', 'members' ),
      'description' => __( 'Allows access to MemberPress Members screen.', 'members' )
    ],
    'create_mepr_members' => [
      'label'       => __( 'Members: Create MemberPress Members', 'members' ),
      'description' => __( 'Allows access to create Members. Roles will also need to be given access to create WordPress users.', 'members' )
    ],
    'edit_mepr_members' => [
      'label'       => __( 'Members: Edit MemberPress Members', 'members' ),
      'description' => __( 'Allows access to edit Members. Roles will also need to given access to edit WordPress users.', 'members' )
    ],
    'delete_mepr_members' => [
      'label'       => __( 'Members: Delete MemberPress Members', 'members' ),
      'description' => __( 'Allows access to delete Members. Roles will also need to given access to delete WordPress users.', 'members' )
    ],

    //Transactions
    'view_mepr_transactions' => [
      'label'       => __( 'Transactions: View MemberPress Transactions', 'members' ),
      'description' => __( 'Allows access to MemberPress Transactions screen.', 'members' )
    ],
    'create_mepr_transactions' => [
      'label'       => __( 'Transactions: Create MemberPress Transactions', 'members' ),
      'description' => __( 'Allows access to create Transactions.', 'members' )
    ],
    'edit_mepr_transactions' => [
      'label'       => __( 'Transactions: Edit MemberPress Transactions', 'members' ),
      'description' => __( 'Allows access to edit Transactions.', 'members' )
    ],
    'delete_mepr_transactions' => [
      'label'       => __( 'Transactions: Delete MemberPress Transactions', 'members' ),
      'description' => __( 'Allows access to delete Transactions.', 'members' )
    ],

    //Subscriptions
    'view_mepr_subscriptions' => [
      'label'       => __( 'Subscriptions: View MemberPress Subscriptions', 'members' ),
      'description' => __( 'Allows access to MemberPress Subscriptions screen.', 'members' )
    ],
    'cancel_mepr_subscriptions' => [
      'label'       => __( 'Subscriptions: Cancel MemberPress Subscriptions', 'members' ),
      'description' => __( 'Allows access to cancel subscriptions.', 'members' )
    ],
    'pause_mepr_subscriptions' => [
      'label'       => __( 'Subscriptions: Pause MemberPress Subscriptions', 'members' ),
      'description' => __( 'Allows access to pause subscriptions.', 'members' )
    ],
    'resume_mepr_subscriptions' => [
      'label'       => __( 'Subscriptions: Resume MemberPress Subscriptions', 'members' ),
      'description' => __( 'Allows access to resume subscriptions.', 'members' )
    ],
    'create_mepr_subscriptions' => [
      'label'       => __( 'Subscriptions: Create MemberPress Subscriptions', 'members' ),
      'description' => __( 'Allows access to create Subscriptions.', 'members' )
    ],
    'edit_mepr_subscriptions' => [
      'label'       => __( 'Subscriptions: Edit MemberPress Subscriptions', 'members' ),
      'description' => __( 'Allows access to edit Subscriptions.', 'members' )
    ],
    'delete_mepr_subscriptions' => [
      'label'       => __( 'Subscriptions: Delete MemberPress Subscriptions', 'members' ),
      'description' => __( 'Allows access to delete Subscriptions.', 'members' )
    ],

    //Groups
    'edit_mepr_groups' => [
      'label'       => __( 'Groups: Edit', 'members' ),
      'description' => __( 'Allows access to edit groups.', 'members' )
    ],
    'edit_others_groups' => [
      'label'       => __( 'Groups: Edit Others', 'members' ),
      'description' => __( 'Allows access to edit others\' groups.', 'members' )
    ],
    'delete_groups' => [
      'label'       => __( 'Groups: Delete', 'members' ),
      'description' => __( 'Allows access to delete groups.', 'members' )
    ],
    'publish_groups' => [
      'label'       => __( 'Groups: Publish', 'members' ),
      'description' => __( 'Allows access to publish groups.', 'members' )
    ],
    'read_private_groups' => [
      'label'       => __( 'Groups: Read Private', 'members' ),
      'description' => __( 'Allows access to view private groups.', 'members' )
    ],
    'delete_private_groups' => [
      'label'       => __( 'Groups: Delete Private Groups', 'members' ),
      'description' => __( 'Allows access to delete private groups.', 'members' )
    ],
    'delete_published_groups' => [
      'label'       => __( 'Groups: Delete Published', 'members' ),
      'description' => __( 'Allows access to delete published groups.', 'members' )
    ],
    'delete_others_groups' => [
      'label'       => __( 'Groups: Delete Others', 'members' ),
      'description' => __( 'Allows access to delete others\' groups.', 'members' )
    ],
    'edit_private_groups' => [
      'label'       => __( 'Groups: Edit Private', 'members' ),
      'description' => __( 'Allows access to edit private groups.', 'members' )
    ],
    'edit_published_groups' => [
      'label'       => __( 'Groups: Edit Published', 'members' ),
      'description' => __( 'Allows access to edit published groups.', 'members' )
    ],

    //Products
    'edit_mepr_products' => [
      'label'       => __( 'Products: Edit', 'members' ),
      'description' => __( 'Allows access to edit products.', 'members' )
    ],
    'edit_others_products' => [
      'label'       => __( 'Products: Edit Others', 'members' ),
      'description' => __( 'Allows access to edit others\' products.', 'members' )
    ],
    'delete_products' => [
      'label'       => __( 'Products: Delete', 'members' ),
      'description' => __( 'Allows access to delete products.', 'members' )
    ],
    'publish_products' => [
      'label'       => __( 'Products: Publish', 'members' ),
      'description' => __( 'Allows access to publish products.', 'members' )
    ],
    'read_private_products' => [
      'label'       => __( 'Products: Read Private', 'members' ),
      'description' => __( 'Allows access to view private products.', 'members' )
    ],
    'delete_private_products' => [
      'label'       => __( 'Products: Delete Private', 'members' ),
      'description' => __( 'Allows access to delete private products.', 'members' )
    ],
    'delete_published_products' => [
      'label'       => __( 'Products: Delete Published', 'members' ),
      'description' => __( 'Allows access to delete published products.', 'members' )
    ],
    'delete_others_products' => [
      'label'       => __( 'Products: Delete Others', 'members' ),
      'description' => __( 'Allows access to delete others\' products.', 'members' )
    ],
    'edit_private_products' => [
      'label'       => __( 'Products: Edit Private', 'members' ),
      'description' => __( 'Allows access to edit private products.', 'members' )
    ],
    'edit_published_products' => [
      'label'       => __( 'Products: Edit Published', 'members' ),
      'description' => __( 'Allows access to edit published products.', 'members' )
    ],

    //Coupons
    'edit_mepr_coupons' => [
      'label'       => __( 'Coupons: Edit', 'members' ),
      'description' => __( 'Allows access to edit coupons.', 'members' )
    ],
    'edit_others_coupons' => [
      'label'       => __( 'Coupons: Edit Others', 'members' ),
      'description' => __( 'Allows access to edit others\' coupons.', 'members' )
    ],
    'delete_coupons' => [
      'label'       => __( 'Coupons: Delete', 'members' ),
      'description' => __( 'Allows access to delete coupons.', 'members' )
    ],
    'publish_coupons' => [
      'label'       => __( 'Coupons: Publish', 'members' ),
      'description' => __( 'Allows access to publish coupons.', 'members' )
    ],
    'read_private_coupons' => [
      'label'       => __( 'Coupons: Read Private', 'members' ),
      'description' => __( 'Allows access to view private coupons.', 'members' )
    ],
    'delete_private_coupons' => [
      'label'       => __( 'Coupons: Delete Private', 'members' ),
      'description' => __( 'Allows access to delete private coupons.', 'members' )
    ],
    'delete_published_coupons' => [
      'label'       => __( 'Coupons: Delete Published', 'members' ),
      'description' => __( 'Allows access to delete published coupons.', 'members' )
    ],
    'delete_others_coupons' => [
      'label'       => __( 'Coupons: Delete Others', 'members' ),
      'description' => __( 'Allows access to delete others\' coupons.', 'members' )
    ],
    'edit_private_coupons' => [
      'label'       => __( 'Coupons: Edit Private', 'members' ),
      'description' => __( 'Allows access to edit private coupons.', 'members' )
    ],
    'edit_published_coupons' => [
      'label'       => __( 'Coupons: Edit Published', 'members' ),
      'description' => __( 'Allows access to edit published coupons.', 'members' )
    ],

    //Reminders
    'edit_mepr_reminders' => [
      'label'       => __( 'Reminders: Edit', 'members' ),
      'description' => __( 'Allows access to edit reminders.', 'members' )
    ],
    'edit_others_reminders' => [
      'label'       => __( 'Reminders: Edit Others', 'members' ),
      'description' => __( 'Allows access to edit others\' reminders.', 'members' )
    ],
    'delete_reminders' => [
      'label'       => __( 'Reminders: Delete', 'members' ),
      'description' => __( 'Allows access to delete reminders.', 'members' )
    ],
    'publish_reminders' => [
      'label'       => __( 'Reminders: Publish', 'members' ),
      'description' => __( 'Allows access to publish reminders.', 'members' )
    ],
    'read_private_reminders' => [
      'label'       => __( 'Reminders: Read Private', 'members' ),
      'description' => __( 'Allows access to view private reminders.', 'members' )
    ],
    'delete_private_reminders' => [
      'label'       => __( 'Reminders: Delete Private', 'members' ),
      'description' => __( 'Allows access to delete private reminders.', 'members' )
    ],
    'delete_published_reminders' => [
      'label'       => __( 'Reminders: Delete Published', 'members' ),
      'description' => __( 'Allows access to delete published reminders.', 'members' )
    ],
    'delete_others_reminders' => [
      'label'       => __( 'Reminders: Delete Others', 'members' ),
      'description' => __( 'Allows access to delete others\' reminders.', 'members' )
    ],
    'edit_private_reminders' => [
      'label'       => __( 'Reminders: Edit Private', 'members' ),
      'description' => __( 'Allows access to edit private reminders.', 'members' )
    ],
    'edit_published_reminders' => [
      'label'       => __( 'Reminders: Edit Published', 'members' ),
      'description' => __( 'Allows access to edit published reminders.', 'members' )
    ],

    //Rules
    'edit_mepr_rules' => [
      'label'       => __( 'Rules: Edit', 'members' ),
      'description' => __( 'Allows access to edit rules.', 'members' )
    ],
    'edit_others_rules' => [
      'label'       => __( 'Rules: Edit Others', 'members' ),
      'description' => __( 'Allows access to edit others\' rules.', 'members' )
    ],
    'delete_rules' => [
      'label'       => __( 'Rules: Delete', 'members' ),
      'description' => __( 'Allows access to delete rules.', 'members' )
    ],
    'publish_rules' => [
      'label'       => __( 'Rules: Publish', 'members' ),
      'description' => __( 'Allows access to publish rules.', 'members' )
    ],
    'read_private_rules' => [
      'label'       => __( 'Rules: Read Private', 'members' ),
      'description' => __( 'Allows access to view private rules.', 'members' )
    ],
    'delete_private_rules' => [
      'label'       => __( 'Rules: Delete Private', 'members' ),
      'description' => __( 'Allows access to delete private rules.', 'members' )
    ],
    'delete_published_rules' => [
      'label'       => __( 'Rules: Delete Published', 'members' ),
      'description' => __( 'Allows access to delete published rules.', 'members' )
    ],
    'delete_others_rules' => [
      'label'       => __( 'Rules: Delete Others', 'members' ),
      'description' => __( 'Allows access to delete others\' rules.', 'members' )
    ],
    'edit_private_rules' => [
      'label'       => __( 'Rules: Edit Private', 'members' ),
      'description' => __( 'Allows access to edit private rules.', 'members' )
    ],
    'edit_published_rules' => [
      'label'       => __( 'Rules: Edit Published', 'members' ),
      'description' => __( 'Allows access to edit published rules.', 'members' )
    ]
  ];
}
