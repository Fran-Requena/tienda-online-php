-- Ejecutar una vez en la base existente antes de utilizar el proceso de compra.
ALTER TABLE pedidos ADD COLUMN subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0 AFTER fechaPedido;
UPDATE pedidos SET subtotal = totalPedido - gastosEnvio;
