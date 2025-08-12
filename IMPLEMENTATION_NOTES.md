# 节点统计页面新增列功能实现

## 实现概要
本次修改在节点统计页面的记录数列后面增加了两个新列：
- **达量限速列**：显示数据库node表中excessive_speed_limit字段的值
- **节点限速列**：显示数据库node表中speed_limit字段的值

## 修改的文件

### 1. 语言文件 (lang/)
- `chinese-cn.php` - 添加中文简体语言支持
- `chinese-tw.php` - 添加中文繁体语言支持  
- `english.php` - 添加英文语言支持

新增语言键：
```php
'excessive_speed_limit' => '达量限速', // 中文简体
'excessive_speed_limit' => '達量限速', // 中文繁体
'excessive_speed_limit' => 'Excessive Speed Limit', // 英文
```

### 2. 节点统计模板 (templates/node_stats.php)
- 在表头添加两个新列（位置：记录数列后面）
- 更新JavaScript生成表格行的代码以显示新数据
- 更新所有colspan值从19改为21（增加2列）
- 添加排序功能支持新列

## 技术实现细节

### 数据源
数据已经通过现有的 `v2raysocks_traffic_getNodeTrafficRankings()` 函数从数据库获取：
- `excessive_speed_limit` 字段
- `speed_limit` 字段

### 显示逻辑
- 直接显示数据库字段值，不添加单位后缀
- 当字段值为空时显示 "-"
- 支持按这两个字段排序（字符串排序）

### 表格结构变化
- 原表格列数：19列
- 新表格列数：21列
- 新增列位置：在"记录数"和"国家"列之间

## 测试验证

1. **PHP语法检查**：所有修改的PHP文件通过语法验证
2. **功能测试**：创建模拟测试验证数据显示正确
3. **UI预览**：生成HTML预览文件确认视觉效果

## 代码兼容性
- 代码向后兼容，如果数据库中不存在这些字段，会显示为空值
- 现有功能不受影响
- 支持所有三种语言环境

## 部署说明
本修改为纯前端显示修改，无需数据库迁移。确保数据库node表中已存在相应字段：
- `excessive_speed_limit` (可选)
- `speed_limit` (可选)