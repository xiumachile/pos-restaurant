use sha2::{Digest, Sha256};

pub fn generar_token() -> String {
    let mut bytes = [0u8; 32];

    rand::fill(&mut bytes);

    hex::encode(bytes)
}

pub fn hash_token(token: &str) -> String {
    let digest = Sha256::digest(token.as_bytes());
    hex::encode(digest)
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn token_tiene_longitud_correcta() {
        let token = generar_token();

        assert_eq!(token.len(), 64);
        assert!(token.bytes().all(|b| b.is_ascii_hexdigit()));
    }

    #[test]
    fn tokens_son_diferentes() {
        let token1 = generar_token();
        let token2 = generar_token();

        assert_ne!(token1, token2);
    }

    #[test]
    fn hash_es_determinista() {
        let token = "token-de-prueba";

        assert_eq!(hash_token(token), hash_token(token));
    }

    #[test]
    fn tokens_diferentes_generan_hashes_diferentes() {
        let token1 = generar_token();
        let token2 = generar_token();

        assert_ne!(hash_token(&token1), hash_token(&token2));
    }

    #[test]
    fn hash_no_contiene_token_original() {
        let token = generar_token();
        let hash = hash_token(&token);

        assert_ne!(token, hash);
        assert_eq!(hash.len(), 64);
    }
}
