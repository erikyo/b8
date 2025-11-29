=== CHANGELOG ===

=== 0.2.0 ===

### 🔧 **Critical Fixes Documented**
- **Database version 4**: Updated throughout with new schema
- **Security**: MySQL prepared statements now properly documented
- **DBA initialization**: Fixed isInitialized() logic
- **Token normalization**: Standardized on lowercase handling

### 🚀 **New Features Explained**
- **TF-IDF weighting**: Separate section explaining how it improves accuracy
- **N-gram analysis**: Configuration options and performance considerations
- **Enhanced classes**: New lexer/degenerator classes with full config options
- **Batch operations**: Performance optimization details

### 💡 **Migration Guidance**
- **Update from 0.6.***: Dedicated section with step-by-step migration
- **Backward compatibility**: Clear statement that existing code continues working
- **Performance tips**: Specific recommendations for production use

### 📊 **Performance Section**
- **Memory usage**: Honest assessment of overhead (10-30%)
- **Caching strategies**: IDF cache, prepared statements, degenerator cache management
- **Database tuning**: Index recommendations for large datasets