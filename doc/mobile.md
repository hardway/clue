Mobile Web最佳实践

* 将JS脚本分为最小的前置加载部分用defer方式加载，剩余部分用async方式加载
  为保证浏览器兼容性，仍然应该在body结束前才放置script标签
