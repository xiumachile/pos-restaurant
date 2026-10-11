use rusqlite::Connection;
use std::sync::Mutex;

/// Estado global de la base de datos, protegido por un Mutex.
pub struct DbState(pub Mutex<Connection>);
