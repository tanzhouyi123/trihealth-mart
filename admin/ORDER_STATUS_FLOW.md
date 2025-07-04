# 订单状态流程说明

## 订单状态定义

### 1. **Pending (待处理)**
- 订单刚创建时的初始状态
- 等待管理员确认
- 可以进行确认或取消操作

### 2. **Confirmed (已确认)**
- 管理员确认订单后的状态
- 表示订单已接受，准备处理
- 可以进行完成、取消或分配配送员操作

### 3. **Processing (处理中)**
- 订单正在处理中
- 可以分配给配送员
- 可以进行完成操作

### 4. **Completed (已完成)**
- 订单处理完成，准备配送
- 可以进行配送操作
- 可以分配给配送员

### 5. **Delivered (已配送)**
- 订单已成功配送给客户
- 最终状态，不可再修改
- 不可删除

### 6. **Cancelled (已取消)**
- 订单被取消
- 最终状态，不可再修改
- 可以删除

## 状态转换流程

```
Pending → Confirmed → Completed → Delivered
   ↓         ↓           ↓
Cancelled  Cancelled   Cancelled
```

## 操作权限

### 管理员可以执行的操作：

1. **查看订单详情** - 所有状态
2. **确认订单** - 仅 Pending 状态
3. **标记完成** - 仅 Confirmed 状态
4. **标记配送** - 仅 Completed 状态
5. **取消订单** - Pending 和 Confirmed 状态
6. **删除订单** - 除 Delivered 外的所有状态
7. **分配配送员** - 在订单详情页面

### 状态更新规则：

- **Pending → Confirmed**: 管理员确认订单
- **Confirmed → Completed**: 订单处理完成
- **Completed → Delivered**: 订单配送完成
- **Pending/Confirmed → Cancelled**: 订单被取消

## 按钮说明

### 订单列表页面按钮：

- **👁️ 查看**: 查看订单详情
- **✅ 确认**: 将 Pending 订单标记为 Confirmed
- **📦 完成**: 将 Confirmed 订单标记为 Completed
- **🚚 配送**: 将 Completed 订单标记为 Delivered
- **❌ 取消**: 将 Pending/Confirmed 订单标记为 Cancelled
- **🗑️ 删除**: 删除非 Delivered 订单

### 订单详情页面功能：

- **状态更新**: 可以更新订单状态
- **分配配送员**: 可以为订单分配配送员
- **查看详细信息**: 客户信息、订单项目等

## 技术实现

### 文件结构：
- `orders.php` - 订单列表页面
- `view_order.php` - 订单详情页面
- `update_order_status.php` - 状态更新API
- `delete_order.php` - 订单删除API

### 技术特点：
- **AJAX操作**: 状态更新无需页面刷新
- **权限控制**: 根据订单状态显示相应按钮
- **确认对话框**: 防止误操作
- **实时反馈**: 操作后立即显示结果

## 使用说明

1. **查看订单**: 点击眼睛图标查看订单详情
2. **确认订单**: 点击绿色勾号确认 Pending 订单
3. **完成订单**: 点击蓝色盒子图标标记 Confirmed 订单为完成
4. **配送订单**: 点击绿色卡车图标标记 Completed 订单为已配送
5. **取消订单**: 点击黄色叉号取消 Pending 或 Confirmed 订单
6. **删除订单**: 点击红色垃圾桶删除非 Delivered 订单

## 注意事项

1. **数据完整性**: 已配送的订单不可删除
2. **状态顺序**: 订单状态必须按顺序更新
3. **权限验证**: 所有操作都需要管理员权限
4. **日志记录**: 建议记录所有状态变更操作
5. **备份建议**: 定期备份订单数据 