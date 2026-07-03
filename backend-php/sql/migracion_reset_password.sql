-- =============================================================================
--  MIGRACIÓN  ·  Añade las columnas de recuperación de contraseña a `user`.
--  Ejecútalo SOLO si ya tienes la tabla `user` creada de antes (sin estas
--  columnas). Si importas tatoodenda.sql desde cero, NO necesitas este fichero.
-- =============================================================================

USE `tatoodenda`;

ALTER TABLE `user`
  ADD COLUMN `resetToken`       VARCHAR(64) DEFAULT NULL,
  ADD COLUMN `resetTokenExpiry` DATETIME    DEFAULT NULL,
  ADD KEY `idx_user_resettoken` (`resetToken`);
