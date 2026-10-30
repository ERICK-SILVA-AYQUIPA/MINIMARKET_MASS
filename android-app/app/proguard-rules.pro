# kotlinx.serialization: conservar los serializadores generados de los DTO de la API
-keepattributes *Annotation*, InnerClasses, Signature, Exceptions
-dontnote kotlinx.serialization.AnnotationsKt
-keepclassmembers @kotlinx.serialization.Serializable class pe.mass.minimarket.** {
    *** Companion;
    *** INSTANCE;
    kotlinx.serialization.KSerializer serializer(...);
}
-keepclasseswithmembers class pe.mass.minimarket.** {
    kotlinx.serialization.KSerializer serializer(...);
}
-keep,includedescriptorclasses class pe.mass.minimarket.**$$serializer { *; }

# Retrofit: las interfaces con anotaciones y los tipos genericos de retorno
-keep,allowobfuscation,allowshrinking interface retrofit2.Call
-keep,allowobfuscation,allowshrinking class retrofit2.Response
-keep,allowobfuscation,allowshrinking class kotlin.coroutines.Continuation
-keep interface pe.mass.minimarket.data.api.MassApi { *; }
