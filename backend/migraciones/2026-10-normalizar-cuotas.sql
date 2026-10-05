-- Normaliza gastos existentes a las nuevas reglas de cuotas.
-- 1) Revisá primero qué filas de crédito tienen un valor_total distinto del monto
--    (por ejemplo, si cargaste intereses a mano). El paso 3 las sobrescribe.
SELECT id, descripcion, monto, cantidad_cuotas, valor_cuota, valor_total
FROM gastos
WHERE metodo_pago = 'credito' AND valor_total <> monto;

-- 2) Efectivo, débito y billetera virtual: siempre 1 pago por el monto total.
UPDATE gastos
SET cantidad_cuotas = 1, valor_cuota = monto, valor_total = monto
WHERE metodo_pago <> 'credito';

-- 3) Crédito: valor de cuota recalculado igual que el backend (monto / cuotas, sin interés).
UPDATE gastos
SET cantidad_cuotas = LEAST(GREATEST(cantidad_cuotas, 1), 60),
    valor_total = monto,
    valor_cuota = ROUND(monto / LEAST(GREATEST(cantidad_cuotas, 1), 60), 2)
WHERE metodo_pago = 'credito';
