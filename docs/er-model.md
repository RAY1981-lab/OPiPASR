diff --git a/docs/er-model.md b/docs/er-model.md
new file mode 100644
index 0000000000000000000000000000000000000000..85c1a421e239d695c3d9e19d2744e7028bbc279b
--- /dev/null
+++ b/docs/er-model.md
@@ -0,0 +1,16 @@
+# ER-модель (текстовое описание)
+
+## Поток данных
+`ibas_raw_events` -> `incident_events` -> `incidents` -> `model_runs` -> (`forecasts`, `recommendations`) -> `recommendation_decisions`.
+
+## Ключевые связи
+- Один `incident` имеет много `incident_events`.
+- Один `incident` имеет много `model_runs`.
+- Один `model_run` имеет много `forecasts` и `recommendations`.
+- Одна `recommendation` может иметь несколько решений в истории (`recommendation_decisions`).
+- `audit_log` фиксирует любые действия пользователей и сервисов.
+
+## Почему так
+- Хранение `ibas_raw_events.payload` позволяет повторно прогонять нормализацию.
+- Разделение `model_runs` и результатов обеспечивает воспроизводимость прогнозов.
+- Таблица решений по рекомендациям даёт оценку полезности ИИ-советов.
