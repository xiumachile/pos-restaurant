pub mod models;
pub mod pin;
pub mod policy;
pub mod repository;
pub mod service;
pub mod token;

#[cfg(test)]
mod integration_tests;

#[cfg(test)]
mod login_integration_tests;

pub mod session;

#[cfg(test)]
mod session_integration_tests;

#[cfg(test)]
mod session_login_integration_tests;

#[cfg(test)]
mod terminal_lock_integration_tests;

pub mod permissions;
#[cfg(test)]
mod terminal_login_concurrency_tests;

#[cfg(test)]
mod permissions_integration_tests;

#[cfg(test)]
mod permissions_protection_tests;

#[cfg(test)]
mod user_deactivation_protection_tests;

#[cfg(test)]
mod user_deactivation_concurrency_tests;

pub mod roles;

#[cfg(test)]
mod role_deactivation_protection_tests;

#[cfg(test)]
mod role_deactivation_concurrency_tests;

#[cfg(test)]
mod mixed_admin_concurrency_tests;

#[cfg(test)]
mod role_permission_concurrency_tests;

pub mod user_roles;

#[cfg(test)]
mod user_roles_integration_tests;

#[cfg(test)]
mod user_role_change_concurrency_tests;

#[cfg(test)]
mod user_role_permission_concurrency_tests;

#[cfg(test)]
mod permission_immediate_revocation_tests;
