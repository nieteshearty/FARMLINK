# 🤝 Contributing to FARMLINK

Thank you for your interest in contributing to FARMLINK! This document provides guidelines for contributing to the project.

## 🚀 Getting Started

1. **Fork the repository** on GitHub
2. **Clone your fork** locally:
   ```bash
   git clone https://github.com/YOUR_USERNAME/FARMLINK.git
   ```
3. **Set up the development environment** following the README.md instructions
4. **Create a new branch** for your feature:
   ```bash
   git checkout -b feature/your-feature-name
   ```

## 🏗️ Development Guidelines

### **Code Structure**
- Follow the existing 3-role system (Super Admin, Farmer, Buyer)
- Maintain the MVC pattern and directory structure
- Use the established helper classes and utilities
- Keep role-based page organization

### **Database Changes**
- Update `farmlink.sql` for any schema changes
- Test migrations thoroughly
- Document new tables/columns in README.md

### **Frontend Development**
- Maintain responsive design principles
- Follow the agricultural theme and color scheme
- Ensure mobile compatibility (iPhone 12+ optimization)
- Use existing CSS classes and patterns

### **Security Requirements**
- Use prepared statements for all database queries
- Implement proper input validation
- Follow role-based access control patterns
- Never expose sensitive information in client-side code

## 🧪 Testing

### **Required Testing**
- Test with all three user roles
- Verify mobile responsiveness
- Test location services and mapping features
- Validate e-commerce functionality (cart, orders, payments)
- Test delivery management features

### **Demo Accounts**
Use these accounts for testing:
- **Super Admin:** `superadmin@farmlink.com` / `password123`
- **Farmer:** `farmer1@farmlink.app` / `password123`
- **Buyer:** `buyer1@farmlink.app` / `password123`

## 📝 Commit Guidelines

### **Commit Message Format**
```
🎯 Type: Brief description

📋 Detailed description of changes
- Bullet point 1
- Bullet point 2

🧪 Testing: What was tested
🔧 Impact: What this affects
```

### **Commit Types**
- `✨ Feature:` New functionality
- `🐛 Fix:` Bug fixes
- `🔧 Refactor:` Code improvements
- `📱 Mobile:` Mobile-specific changes
- `🗺️ Maps:` Location/mapping features
- `🛒 Cart:` E-commerce functionality
- `🚚 Delivery:` Delivery management
- `🔐 Security:` Security improvements
- `📚 Docs:` Documentation updates

## 🔄 Pull Request Process

1. **Update documentation** if needed
2. **Test thoroughly** with all user roles
3. **Update README.md** for new features
4. **Create pull request** with detailed description
5. **Link any related issues**

### **Pull Request Template**
```markdown
## 🎯 Description
Brief description of changes

## 🧪 Testing
- [ ] Tested with Super Admin role
- [ ] Tested with Farmer role  
- [ ] Tested with Buyer role
- [ ] Mobile responsiveness verified
- [ ] Location services tested

## 📋 Changes
- Change 1
- Change 2

## 🔧 Impact
What parts of the system are affected

## 📸 Screenshots
If applicable, add screenshots
```

## 🚫 What Not to Contribute

- **Test files or debug code** (we maintain a clean production codebase)
- **Admin role functionality** (removed for security)
- **Breaking changes** to the 3-role system
- **Dependencies on paid services** (we use free alternatives)
- **Non-responsive designs** (mobile-first approach required)

## 🎯 Priority Areas for Contribution

### **High Priority**
- Performance optimizations
- Additional payment methods
- Enhanced analytics and reporting
- Advanced search and filtering
- Mobile app development

### **Medium Priority**
- Additional language support
- Enhanced notification system
- Advanced inventory features
- Integration with agricultural APIs
- Automated testing suite

### **Documentation**
- API documentation
- Deployment guides
- Video tutorials
- Translation of documentation

## 🆘 Getting Help

- **Check existing issues** before creating new ones
- **Use discussion threads** for questions
- **Reference the README.md** for setup issues
- **Test with demo accounts** first

## 📄 License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

Thank you for helping make FARMLINK better! 🌾
