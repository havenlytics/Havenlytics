<?php
/**
 * Workspace authentication helpers.
 *
 * Production classes (one responsibility each):
 * - SessionAuthController — login / register / lost / reset / logout / auth nonces
 * - WorkspaceRegistrationStatus — SSOT for registration lifecycle (user meta)
 * - PortalAuthorization — Workspace access booleans only
 * - AgentIdentityService — resolve User → Agent identity only
 * - RegistrationAdminActions — administrator approval UI / mutations only
 * - AgentProvisioner — create/link Agent CPT
 * - CapabilityRegistrar / PortalCapabilities — WP role + caps
 * - RegistrationEmailNotifier — approval emails
 * - IdentityAdminColumns — list columns
 *
 * Publishing an Agent CPT never approves Workspace login.
 *
 * @package HvnlyNab\Workspace\Auth
 */

defined( 'ABSPATH' ) || exit;
