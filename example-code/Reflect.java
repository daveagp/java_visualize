import java.lang.reflect.*;

public class Reflect {
    public static void announce() {
        System.out.println("Someone called announce().");
    }
    public static void main(String[] args) {
        try {
            Method m = Reflect.class.getMethod("announce", null);
            m.invoke(null);
        }
        catch (NoSuchMethodException | IllegalAccessException 
               | InvocationTargetException e) {}
    }
}